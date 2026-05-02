<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Dashboard
{
    private static bool $appointmentColumnEnsured = false;

    private function ensureQuickAccessColumns(): void
    {
        (new Category())->ensureQuickAccessColumns();
    }

    private function ensureAppointmentColumn(): void
    {
        if (self::$appointmentColumnEnsured) {
            return;
        }

        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM visits')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('appointment_status', $columns, true)) {
            $pdo->exec('ALTER TABLE visits ADD COLUMN appointment_status ENUM("walk_in", "appointment") NOT NULL DEFAULT "walk_in" AFTER purpose');
        }

        self::$appointmentColumnEnsured = true;
    }

    public function stats(): array
    {
        $pdo = Database::connection();

        return [
            'today_entries' => (int) $pdo->query('SELECT COUNT(*) FROM visits WHERE entry_at >= CURDATE() AND entry_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)')->fetchColumn(),
            'inside' => (int) $pdo->query("SELECT COUNT(*) FROM visits WHERE status <> 'exited' AND exit_at IS NULL")->fetchColumn(),
            'overdue' => (int) $pdo->query("SELECT COUNT(*) FROM visits WHERE status IN ('overdue', 'department_asked', 'escalated') AND exit_at IS NULL")->fetchColumn(),
            'notifications_today' => (int) $pdo->query('SELECT COUNT(*) FROM notification_logs WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)')->fetchColumn(),
        ];
    }

    public function insideVisits(): array
    {
        $this->ensureAppointmentColumn();

        $stmt = Database::connection()->query(
            "SELECT
                v.id,
                v.category_id,
                v.department_id,
                v.entry_at,
                v.status,
                v.max_duration_minutes_snapshot,
                v.appointment_status,
                v.host_name,
                v.purpose,
                v.entry_note,
                vi.full_name,
                vi.phone,
                vi.company,
                vi.vehicle_plate,
                vi.note AS visitor_note,
                c.name AS category_name,
                c.color AS category_color,
                d.name AS department_name,
                TIMESTAMPDIFF(MINUTE, v.entry_at, NOW()) AS elapsed_minutes
             FROM visits v
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories c ON c.id = v.category_id
             LEFT JOIN departments d ON d.id = v.department_id
             WHERE v.exit_at IS NULL AND v.status <> 'exited'
             ORDER BY v.entry_at DESC
             LIMIT 100"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recentActivity(): array
    {
        $stmt = Database::connection()->query(
            'SELECT
                ve.id,
                ve.event_type,
                ve.event_note,
                ve.created_at,
                u.full_name AS user_name,
                vi.full_name AS visitor_name,
                vi.company,
                vi.vehicle_plate,
                vc.name AS category_name,
                d.name AS department_name
             FROM visit_events ve
             INNER JOIN visits v ON v.id = ve.visit_id
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = v.department_id
             LEFT JOIN users u ON u.id = ve.user_id
             ORDER BY ve.created_at DESC, ve.id DESC
             LIMIT 20'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories(): array
    {
        $this->ensureQuickAccessColumns();

        $stmt = Database::connection()->query(
            "SELECT id, name, color, max_duration_minutes, is_quick_access, quick_access_order
             FROM visitor_categories
             WHERE status = 'active' AND deleted_at IS NULL
             ORDER BY name"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function quickCategories(): array
    {
        $this->ensureQuickAccessColumns();

        $stmt = Database::connection()->query(
            "SELECT id, name, color, max_duration_minutes, quick_access_order
             FROM visitor_categories
             WHERE status = 'active'
               AND deleted_at IS NULL
               AND is_quick_access = 1
             ORDER BY quick_access_order IS NULL, quick_access_order, name
             LIMIT 4"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function departments(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, name
             FROM departments
             WHERE status = 'active' AND deleted_at IS NULL
             ORDER BY name"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function visitorSuggestions(): array
    {
        $this->ensureAppointmentColumn();

        $stmt = Database::connection()->query(
            'SELECT
                vi.id,
                vi.full_name,
                vi.phone,
                vi.company,
                vi.vehicle_plate,
                vi.note,
                latest.category_id,
                latest.department_id,
                latest.host_name,
                latest.appointment_status
             FROM visitors vi
             LEFT JOIN (
                SELECT visitor_id, MAX(id) AS latest_visit_id
                FROM visits
                GROUP BY visitor_id
             ) latest_visit ON latest_visit.visitor_id = vi.id
             LEFT JOIN visits latest ON latest.id = latest_visit.latest_visit_id
             WHERE vi.deleted_at IS NULL
             ORDER BY vi.updated_at DESC, vi.id DESC
             LIMIT 200'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function pendingVerifications(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                dv.id,
                dv.escalation_level,
                dv.question_text,
                dv.sent_at,
                dv.expires_at,
                v.id AS visit_id,
                v.entry_at,
                vi.full_name,
                vi.company,
                vc.name AS category_name,
                d.name AS department_name,
                TIMESTAMPDIFF(MINUTE, v.entry_at, NOW()) AS elapsed_minutes
             FROM department_verifications dv
             INNER JOIN visits v ON v.id = dv.visit_id
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = dv.department_id
             LEFT JOIN departments managed ON managed.id = dv.department_id
             WHERE dv.answer IS NULL
               AND v.exit_at IS NULL
               AND (
                    dv.sent_to_user_id = :sent_to_user_id
                    OR managed.manager_user_id = :manager_user_id
               )
             ORDER BY dv.expires_at ASC, dv.sent_at ASC'
        );
        $stmt->execute([
            'sent_to_user_id' => $userId,
            'manager_user_id' => $userId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function liveSignature(int $userId): string
    {
        $pdo = Database::connection();
        $latestEventId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM visit_events')->fetchColumn();
        $latestNotificationId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM notification_logs')->fetchColumn();
        $insideCount = (int) $pdo->query("SELECT COUNT(*) FROM visits WHERE exit_at IS NULL AND status <> 'exited'")->fetchColumn();
        $pendingCount = 0;

        if ($userId > 0) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM department_verifications dv
                 INNER JOIN visits v ON v.id = dv.visit_id
                 LEFT JOIN departments managed ON managed.id = dv.department_id
                 WHERE dv.answer IS NULL
                   AND v.exit_at IS NULL
                   AND (
                        dv.sent_to_user_id = :sent_to_user_id
                        OR managed.manager_user_id = :manager_user_id
                   )'
            );
            $stmt->execute([
                'sent_to_user_id' => $userId,
                'manager_user_id' => $userId,
            ]);
            $pendingCount = (int) $stmt->fetchColumn();
        }

        return sha1(json_encode([
            'latest_event_id' => $latestEventId,
            'latest_notification_id' => $latestNotificationId,
            'inside_count' => $insideCount,
            'pending_count' => $pendingCount,
        ], JSON_UNESCAPED_UNICODE));
    }
}
