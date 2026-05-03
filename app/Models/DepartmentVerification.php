<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\Notifications\NotificationService;
use PDO;

final class DepartmentVerification
{
    public function answerByToken(string $token, string $answer): array
    {
        if (!in_array($answer, ['yes', 'no'], true)) {
            return ['ok' => false, 'message' => 'Cevap değeri geçersiz.'];
        }

        $verification = $this->findByToken($token);
        if (!$verification) {
            return ['ok' => false, 'message' => 'Bu bağlantı geçersiz veya doğrulama kaydı bulunamadı.'];
        }

        if (!empty($verification['answer'])) {
            return ['ok' => false, 'message' => 'Bu soru daha önce cevaplanmış.'];
        }

        $this->applyAnswer($verification, null, $answer);

        return [
            'ok' => true,
            'message' => $answer === 'yes'
                ? 'Evet cevabı kaydedildi. Güvenlik ekibi bilgilendirildi.'
                : 'Hayır cevabı kaydedildi. Eskalasyon süreci başlatıldı.',
        ];
    }

    public function answer(int $verificationId, int $userId, string $answer): bool
    {
        if (!in_array($answer, ['yes', 'no'], true)) {
            return false;
        }

        $verification = $this->findForUser($verificationId, $userId);
        if (!$verification) {
            return false;
        }

        $this->applyAnswer($verification, $userId, $answer);

        return true;
    }

    private function applyAnswer(array $verification, ?int $userId, string $answer): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $verificationStmt = $pdo->prepare(
                'UPDATE department_verifications
                 SET answer = :answer,
                     answered_at = NOW(),
                     escalation_triggered = CASE WHEN :answer_for_escalation = "no" THEN 1 ELSE escalation_triggered END
                 WHERE id = :id AND answer IS NULL'
            );
            $verificationStmt->execute([
                'id' => (int) $verification['id'],
                'answer' => $answer,
                'answer_for_escalation' => $answer,
            ]);

            $visitStatus = $answer === 'yes' ? 'department_approved' : 'escalated';
            $eventType = $answer === 'yes' ? 'department_answer_yes' : 'department_answer_no';
            $eventNote = $answer === 'yes'
                ? 'Ziyaretçinin ilgili kişiyle beraber olduğu onaylandı.'
                : 'Ziyaretçinin ilgili kişiyle beraber olmadığı bildirildi.';

            $visitStmt = $pdo->prepare(
                'UPDATE visits
                 SET status = :status,
                     department_answer = :answer,
                     department_answer_at = NOW(),
                     department_answer_by_user_id = :user_id,
                     escalated_at = CASE WHEN :answer_for_escalation = "no" THEN NOW() ELSE escalated_at END
                 WHERE id = :visit_id'
            );
            $visitStmt->execute([
                'status' => $visitStatus,
                'answer' => $answer,
                'answer_for_escalation' => $answer,
                'user_id' => $userId,
                'visit_id' => (int) $verification['visit_id'],
            ]);

            $eventStmt = $pdo->prepare(
                'INSERT INTO visit_events (visit_id, user_id, event_type, event_note)
                 VALUES (:visit_id, :user_id, :event_type, :event_note)'
            );
            $eventStmt->execute([
                'visit_id' => (int) $verification['visit_id'],
                'user_id' => $userId,
                'event_type' => $eventType,
                'event_note' => $eventNote,
            ]);

            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }

        if ($answer === 'no') {
            (new NotificationService())->queueForVisitEvent('department_answer_no', (int) $verification['visit_id']);
        }
    }

    private function findForUser(int $verificationId, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT dv.*
             FROM department_verifications dv
             LEFT JOIN departments d ON d.id = dv.department_id
             WHERE dv.id = :id
               AND dv.answer IS NULL
               AND (
                    dv.sent_to_user_id = :sent_to_user_id
                    OR (dv.escalation_level = 0 AND d.manager_user_id = :manager_user_id)
               )
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $verificationId,
            'sent_to_user_id' => $userId,
            'manager_user_id' => $userId,
        ]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        return $verification ?: null;
    }

    private function findByToken(string $token): ?array
    {
        $this->ensureResponseTokenColumn();

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM department_verifications
             WHERE response_token = :token
             LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        return $verification ?: null;
    }

    private function ensureResponseTokenColumn(): void
    {
        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM department_verifications')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('response_token', $columns, true)) {
            $pdo->exec('ALTER TABLE department_verifications ADD COLUMN response_token VARCHAR(64) NULL AFTER question_text');
        }
    }
}
