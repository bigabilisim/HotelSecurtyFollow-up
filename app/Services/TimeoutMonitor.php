<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Category;
use App\Models\Settings;
use App\Services\Notifications\NotificationService;
use PDO;

final class TimeoutMonitor
{
    private ?array $globalEscalationSettings = null;

    public function process(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $this->ensureEscalationWorkflowColumns();

        return [
            'warnings' => $this->queueWarnings($limit),
            'overdue' => $this->markOverdue($limit),
            'questions' => $this->askDepartments($limit),
            'escalations' => $this->escalateNoResponses($limit),
        ];
    }

    private function queueWarnings(int $limit): int
    {
        $visits = $this->warningVisits($limit);
        $count = 0;

        foreach ($visits as $visit) {
            if (!$this->addEventIfMissing((int) $visit['id'], 'timeout_warning', 'İçeride kalma süresi uyarısı oluşturuldu.')) {
                continue;
            }

            (new NotificationService())->queueForVisitEvent('timeout_warning', (int) $visit['id'], [
                'remaining_minutes' => max(0, (int) $visit['max_duration_minutes_snapshot'] - (int) $visit['elapsed_minutes']),
            ]);
            $count++;
        }

        return $count;
    }

    private function markOverdue(int $limit): int
    {
        $visits = $this->overdueVisits($limit);
        $count = 0;

        foreach ($visits as $visit) {
            $stmt = Database::connection()->prepare(
                "UPDATE visits
                 SET status = 'overdue'
                 WHERE id = :id
                   AND status = 'inside'
                   AND exit_at IS NULL"
            );
            $stmt->execute(['id' => (int) $visit['id']]);

            if ($stmt->rowCount() <= 0) {
                continue;
            }

            $this->addEvent((int) $visit['id'], 'note', 'İçeride kalma süresi doldu.');
            $count++;
        }

        return $count;
    }

    private function askDepartments(int $limit): int
    {
        $visits = $this->questionVisits($limit);
        $count = 0;

        foreach ($visits as $visit) {
            $visit = $this->withGlobalEscalation($visit);
            $managerUserId = $visit['manager_user_id'] ? (int) $visit['manager_user_id'] : null;
            $nextLevel = $this->nextConfiguredLevel($visit, 0);
            $escalationAfter = $nextLevel ? $this->minutesForLevel($visit, $nextLevel) : null;
            $questionText = $visit['visitor_name'] . ' halen sizinle beraber mi?';
            $fallbackLevel = (!$this->hasDepartmentMailRecipient($visit) && $nextLevel) ? $nextLevel : null;
            $responseToken = $this->newResponseToken();

            if ($fallbackLevel) {
                $this->askFallbackEscalation($visit, $fallbackLevel);
                $count++;
                continue;
            }

            $pdo = Database::connection();
            $pdo->beginTransaction();

            try {
                $updateStmt = $pdo->prepare(
                    "UPDATE visits
                     SET status = 'department_asked',
                         department_question_sent_at = NOW()
                     WHERE id = :id
                       AND exit_at IS NULL
                       AND department_question_sent_at IS NULL"
                );
                $updateStmt->execute(['id' => (int) $visit['id']]);

                if ($updateStmt->rowCount() <= 0) {
                    $pdo->rollBack();
                    continue;
                }

                $expiresAt = $escalationAfter ? date('Y-m-d H:i:s', time() + ($escalationAfter * 60)) : null;
                $verificationStmt = $pdo->prepare(
                    'INSERT INTO department_verifications (
                        visit_id,
                        department_id,
                        sent_to_user_id,
                        question_text,
                        response_token,
                        escalation_level,
                        expires_at
                     ) VALUES (
                        :visit_id,
                        :department_id,
                        :sent_to_user_id,
                        :question_text,
                        :response_token,
                        0,
                        :expires_at
                     )'
                );
                $verificationStmt->execute([
                    'visit_id' => (int) $visit['id'],
                    'department_id' => $visit['department_id'] ?: null,
                    'sent_to_user_id' => $managerUserId,
                    'question_text' => $questionText,
                    'response_token' => $responseToken,
                    'expires_at' => $expiresAt,
                ]);

                $this->addEvent((int) $visit['id'], 'department_question', 'Departman amirine süre aşımı sorusu gönderildi.');
                $pdo->commit();
            } catch (\Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }

            (new NotificationService())->queueForVisitEvent('department_question', (int) $visit['id'], [
                'question_text' => $questionText,
                'expires_after_minutes' => $escalationAfter,
                ...$this->actionLinks($responseToken),
            ]);
            $count++;
        }

        return $count;
    }

    private function escalateNoResponses(int $limit): int
    {
        $verifications = $this->expiredVerifications($limit);
        $count = 0;

        foreach ($verifications as $verification) {
            $verification = $this->withGlobalEscalation($verification);
            $currentLevel = (int) ($verification['escalation_level'] ?? 0);
            $nextLevel = $this->nextConfiguredLevel($verification, $currentLevel);
            $pdo = Database::connection();
            $pdo->beginTransaction();

            try {
                if (!$nextLevel) {
                    $holdStmt = $pdo->prepare(
                        'UPDATE department_verifications
                         SET escalation_triggered = 1
                         WHERE id = :id
                           AND answer IS NULL
                           AND escalation_triggered = 0'
                    );
                    $holdStmt->execute(['id' => (int) $verification['id']]);

                    if ($holdStmt->rowCount() > 0) {
                        $this->addEvent((int) $verification['visit_id'], 'escalation', $this->levelLabel($currentLevel) . ' seviyesinde cevap bekleniyor. Daha üst amir tanımlı değil.');
                        $count++;
                    }

                    $pdo->commit();
                    continue;
                }

                $verificationStmt = $pdo->prepare(
                    "UPDATE department_verifications
                     SET answer = 'no_response',
                         answered_at = NOW(),
                         escalation_triggered = 1
                     WHERE id = :id
                       AND answer IS NULL
                       AND escalation_triggered = 0"
                );
                $verificationStmt->execute(['id' => (int) $verification['id']]);

                if ($verificationStmt->rowCount() <= 0) {
                    $pdo->rollBack();
                    continue;
                }

                $expiresAt = $this->expiresAtForNextLevel($verification, $nextLevel);
                $questionText = $verification['visitor_name'] . ' için ' . $this->levelLabel($currentLevel) . ' cevabı gelmedi. Kontrolünüzde mi?';
                $nextUserId = (int) $verification['escalation_level_' . $nextLevel . '_user_id'];
                $responseToken = $this->newResponseToken();

                $newVerificationStmt = $pdo->prepare(
                    'INSERT INTO department_verifications (
                        visit_id,
                        department_id,
                        sent_to_user_id,
                        question_text,
                        response_token,
                        escalation_level,
                        expires_at
                     ) VALUES (
                        :visit_id,
                        :department_id,
                        :sent_to_user_id,
                        :question_text,
                        :response_token,
                        :escalation_level,
                        :expires_at
                     )'
                );
                $newVerificationStmt->execute([
                    'visit_id' => (int) $verification['visit_id'],
                    'department_id' => $verification['department_id'] ?: null,
                    'sent_to_user_id' => $nextUserId,
                    'question_text' => $questionText,
                    'response_token' => $responseToken,
                    'escalation_level' => $nextLevel,
                    'expires_at' => $expiresAt,
                ]);

                $visitStmt = $pdo->prepare(
                    "UPDATE visits
                     SET status = 'escalated',
                         department_answer = 'no_response',
                         department_answer_at = NOW(),
                         current_escalation_level = :current_escalation_level,
                         escalated_at = COALESCE(escalated_at, NOW())
                     WHERE id = :visit_id
                       AND exit_at IS NULL"
                );
                $visitStmt->execute([
                    'visit_id' => (int) $verification['visit_id'],
                    'current_escalation_level' => $nextLevel,
                ]);

                $this->addEvent((int) $verification['visit_id'], 'escalation', $this->levelLabel($nextLevel) . ' amirine eskalasyon oluşturuldu.');
                $pdo->commit();
            } catch (\Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }

            (new NotificationService())->queueDirectUser(
                (int) $verification['visit_id'],
                $nextUserId,
                'Otel Güvenlik: ' . $this->levelLabel($nextLevel) . ' Eskalasyon',
                $questionText,
                $this->actionLinks($responseToken)
            );
            $count++;
        }

        return $count;
    }

    private function warningVisits(int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                v.id,
                v.max_duration_minutes_snapshot,
                TIMESTAMPDIFF(MINUTE, v.entry_at, NOW()) AS elapsed_minutes
             FROM visits v
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             WHERE v.exit_at IS NULL
               AND v.status IN ("inside", "overdue", "department_asked")
               AND v.max_duration_minutes_snapshot IS NOT NULL
               AND TIMESTAMPDIFF(MINUTE, v.entry_at, NOW()) >= GREATEST(
                    CAST(v.max_duration_minutes_snapshot AS SIGNED) - CAST(vc.warning_before_minutes AS SIGNED),
                    0
               )
               AND NOT EXISTS (
                    SELECT 1
                    FROM visit_events ve
                    WHERE ve.visit_id = v.id
                      AND ve.event_type = "timeout_warning"
               )
             ORDER BY v.entry_at ASC
             LIMIT ' . $limit
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function overdueVisits(int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT v.id
             FROM visits v
             WHERE v.exit_at IS NULL
               AND v.status = "inside"
               AND v.max_duration_minutes_snapshot IS NOT NULL
               AND TIMESTAMPDIFF(MINUTE, v.entry_at, NOW()) >= v.max_duration_minutes_snapshot
             ORDER BY v.entry_at ASC
             LIMIT ' . $limit
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function questionVisits(int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                v.id,
                v.department_id,
                vi.full_name AS visitor_name,
                d.manager_user_id,
                d.email AS department_email,
                manager.email AS manager_email
             FROM visits v
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = v.department_id
             LEFT JOIN users manager ON manager.id = d.manager_user_id
                AND manager.status = "active"
                AND manager.deleted_at IS NULL
             WHERE v.exit_at IS NULL
               AND v.status IN ("inside", "overdue")
               AND v.department_question_sent_at IS NULL
               AND v.max_duration_minutes_snapshot IS NOT NULL
               AND vc.requires_department_approval = 1
               AND TIMESTAMPDIFF(MINUTE, v.entry_at, NOW()) >= v.max_duration_minutes_snapshot
             ORDER BY v.entry_at ASC
             LIMIT ' . $limit
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function askFallbackEscalation(array $visit, int $level): void
    {
        $nextLevel = $this->nextConfiguredLevel($visit, $level);
        $expiresAt = $nextLevel
            ? date('Y-m-d H:i:s', time() + ($this->minutesForLevel($visit, $nextLevel) * 60))
            : null;
        $nextUserId = (int) $visit['escalation_level_' . $level . '_user_id'];
        $questionText = $visit['visitor_name'] . ' için departman amiri veya departman e-postası bulunamadı. Kontrolünüzde mi?';
        $responseToken = $this->newResponseToken();

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $updateStmt = $pdo->prepare(
                "UPDATE visits
                 SET status = 'escalated',
                     department_question_sent_at = NOW(),
                     department_answer = 'no_response',
                     department_answer_at = NOW(),
                     current_escalation_level = :current_escalation_level,
                     escalated_at = COALESCE(escalated_at, NOW())
                 WHERE id = :id
                   AND exit_at IS NULL
                   AND department_question_sent_at IS NULL"
            );
            $updateStmt->execute([
                'id' => (int) $visit['id'],
                'current_escalation_level' => $level,
            ]);

            if ($updateStmt->rowCount() <= 0) {
                $pdo->rollBack();
                return;
            }

            $verificationStmt = $pdo->prepare(
                'INSERT INTO department_verifications (
                    visit_id,
                    department_id,
                    sent_to_user_id,
                    question_text,
                    response_token,
                    escalation_level,
                    expires_at
                 ) VALUES (
                    :visit_id,
                    :department_id,
                    :sent_to_user_id,
                    :question_text,
                    :response_token,
                    :escalation_level,
                    :expires_at
                 )'
            );
            $verificationStmt->execute([
                'visit_id' => (int) $visit['id'],
                'department_id' => $visit['department_id'] ?: null,
                'sent_to_user_id' => $nextUserId,
                'question_text' => $questionText,
                'response_token' => $responseToken,
                'escalation_level' => $level,
                'expires_at' => $expiresAt,
            ]);

            $this->addEvent((int) $visit['id'], 'escalation', 'Departman amiri/e-postası olmadığı için ' . $this->levelLabel($level) . ' amirine yönlendirildi.');
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }

        (new NotificationService())->queueDirectUser(
            (int) $visit['id'],
            $nextUserId,
            'Otel Güvenlik: ' . $this->levelLabel($level) . ' Eskalasyon',
            $questionText,
            $this->actionLinks($responseToken)
        );
    }

    private function expiredVerifications(int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                dv.id,
                dv.visit_id,
                dv.department_id,
                dv.escalation_level,
                vi.full_name AS visitor_name
             FROM department_verifications dv
             INNER JOIN visits v ON v.id = dv.visit_id
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             WHERE dv.answer IS NULL
               AND dv.escalation_triggered = 0
               AND dv.expires_at IS NOT NULL
               AND dv.expires_at <= NOW()
               AND v.exit_at IS NULL
             ORDER BY dv.expires_at ASC
             LIMIT ' . $limit
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function withGlobalEscalation(array $row): array
    {
        return array_merge($row, $this->globalEscalationSettings());
    }

    private function actionLinks(string $responseToken): array
    {
        return [
            'action_yes_url' => $this->absoluteRoute('/verifications/respond', [
                'token' => $responseToken,
                'answer' => 'yes',
            ]),
            'action_no_url' => $this->absoluteRoute('/verifications/respond', [
                'token' => $responseToken,
                'answer' => 'no',
            ]),
        ];
    }

    private function absoluteRoute(string $path, array $params = []): string
    {
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
        }

        return $baseUrl . route($path, $params);
    }

    private function newResponseToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function globalEscalationSettings(): array
    {
        if ($this->globalEscalationSettings !== null) {
            return $this->globalEscalationSettings;
        }

        $settings = (new Settings())->all();
        $chain = [];

        for ($level = 1; $level <= 3; $level++) {
            $chain['escalation_level_' . $level . '_user_id'] = max(0, (int) ($settings['escalation.level_' . $level . '_user_id'] ?? 0));
            $chain['escalation_level_' . $level . '_after_minutes'] = max(0, (int) ($settings['escalation.level_' . $level . '_after_minutes'] ?? 0));
        }

        $this->globalEscalationSettings = $chain;
        return $chain;
    }

    private function nextConfiguredLevel(array $row, int $currentLevel): ?int
    {
        for ($level = $currentLevel + 1; $level <= 3; $level++) {
            if ((int) ($row['escalation_level_' . $level . '_user_id'] ?? 0) > 0) {
                return $level;
            }
        }

        return null;
    }

    private function expiresAtForNextLevel(array $row, int $currentLevel): ?string
    {
        $nextLevel = $this->nextConfiguredLevel($row, $currentLevel);

        if (!$nextLevel) {
            return null;
        }

        return date('Y-m-d H:i:s', time() + ($this->minutesForLevel($row, $nextLevel) * 60));
    }

    private function minutesForLevel(array $row, int $level): int
    {
        $minutes = (int) ($row['escalation_level_' . $level . '_after_minutes'] ?? 0);

        if ($minutes <= 0 && $level === 1) {
            $minutes = (int) ($row['escalation_after_minutes'] ?? 5);
        }

        return max(1, $minutes ?: 5);
    }

    private function hasDepartmentMailRecipient(array $visit): bool
    {
        return trim((string) ($visit['manager_email'] ?? '')) !== ''
            || trim((string) ($visit['department_email'] ?? '')) !== '';
    }

    private function levelLabel(int $level): string
    {
        return $level > 0 ? 'N+' . $level : 'Departman amiri';
    }

    private function ensureEscalationWorkflowColumns(): void
    {
        (new Category())->ensureQuickAccessColumns();

        $pdo = Database::connection();
        $visitColumns = $pdo->query('SHOW COLUMNS FROM visits')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('current_escalation_level', $visitColumns, true)) {
            $pdo->exec('ALTER TABLE visits ADD COLUMN current_escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER department_answer_by_user_id');
        }

        $verificationColumns = $pdo->query('SHOW COLUMNS FROM department_verifications')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('escalation_level', $verificationColumns, true)) {
            $pdo->exec('ALTER TABLE department_verifications ADD COLUMN escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER question_text');
        }

        if (!in_array('response_token', $verificationColumns, true)) {
            $pdo->exec('ALTER TABLE department_verifications ADD COLUMN response_token VARCHAR(64) NULL AFTER question_text');
        }
    }

    private function addEvent(int $visitId, string $eventType, string $note): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO visit_events (visit_id, user_id, event_type, event_note)
             VALUES (:visit_id, NULL, :event_type, :event_note)'
        );
        $stmt->execute([
            'visit_id' => $visitId,
            'event_type' => $eventType,
            'event_note' => $note,
        ]);
    }

    private function addEventIfMissing(int $visitId, string $eventType, string $note): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $existsStmt = $pdo->prepare(
                'SELECT id
                 FROM visit_events
                 WHERE visit_id = :visit_id
                   AND event_type = :event_type
                 LIMIT 1'
            );
            $existsStmt->execute([
                'visit_id' => $visitId,
                'event_type' => $eventType,
            ]);

            if ($existsStmt->fetchColumn()) {
                $pdo->rollBack();
                return false;
            }

            $insertStmt = $pdo->prepare(
                'INSERT INTO visit_events (visit_id, user_id, event_type, event_note)
                 VALUES (:visit_id, NULL, :event_type, :event_note)'
            );
            $insertStmt->execute([
                'visit_id' => $visitId,
                'event_type' => $eventType,
                'event_note' => $note,
            ]);

            $pdo->commit();
            return true;
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
