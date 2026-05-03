<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Core\Database;
use App\Models\MailTemplate;
use PDO;

final class NotificationService
{
    private static bool $actionColumnsEnsured = false;

    public function queueForVisitEvent(string $eventType, int $visitId, array $extra = []): int
    {
        $visit = $this->visit($visitId);

        if (!$visit || (int) ($visit['category_notification_enabled'] ?? 1) !== 1) {
            return 0;
        }

        $rules = $this->rulesFor($eventType, (int) $visit['category_id']);
        $queuedCount = 0;

        foreach ($rules as $rule) {
            if (!$this->passesConditions($rule['condition_json'] ?? null, $visit, $extra)) {
                continue;
            }

            $channels = $this->channelsForRule((int) $rule['id']);
            $recipients = $this->recipientsForRule((int) $rule['id'], $visit);

            foreach ($channels as $channel) {
                foreach ($recipients as $recipient) {
                    $this->createLog($rule, $channel, $visit, $recipient, $extra);
                    $queuedCount++;
                }
            }
        }

        return $queuedCount;
    }

    public function queueSystemEvent(string $eventType, array $payload = []): int
    {
        $rules = $this->rulesFor($eventType, 0);
        $queuedCount = 0;
        $context = ['department_id' => $payload['department_id'] ?? null];

        foreach ($rules as $rule) {
            if (!$this->passesConditions($rule['condition_json'] ?? null, $context, $payload)) {
                continue;
            }

            $channels = $this->channelsForRule((int) $rule['id']);
            $recipients = $this->recipientsForRule((int) $rule['id'], $context);
            $template = $rule['message_template'] ?: $this->defaultSystemTemplate($eventType);
            $message = $this->renderTemplate($template, $context, $payload);

            foreach ($channels as $channel) {
                foreach ($recipients as $recipient) {
                    $this->createSystemLog($rule, $channel, $recipient, $context, $payload, $message);
                    $queuedCount++;
                }
            }
        }

        return $queuedCount;
    }

    public function queueDirectUser(int $visitId, int $userId, string $subject, string $message, array $extra = []): int
    {
        $visit = $this->visit($visitId);
        $recipients = $this->userRecipients($userId);

        if (!$visit || !$recipients) {
            return 0;
        }

        $queuedCount = 0;
        foreach ($this->enabledChannels() as $channel) {
            foreach ($recipients as $recipient) {
                $this->createDirectLog($channel, $visit, $recipient, $subject, $message, $extra);
                $queuedCount++;
            }
        }

        return $queuedCount;
    }

    public function queueDirectUserSystem(int $userId, string $subject, string $message, array $extra = []): int
    {
        $recipients = $this->userRecipients($userId);

        if (!$recipients) {
            return 0;
        }

        $queuedCount = 0;
        foreach ($this->enabledChannels($extra['channel_codes'] ?? null) as $channel) {
            foreach ($recipients as $recipient) {
                $this->createDirectLog($channel, null, $recipient, $subject, $message, $extra);
                $queuedCount++;
            }
        }

        return $queuedCount;
    }

    public function queueMail(string $recipientEmail, string $recipientName, string $subject, string $message, ?string $attachmentPath = null, ?string $attachmentName = null): int
    {
        return $this->queueMailLog($recipientEmail, $recipientName, $subject, $message, $attachmentPath, $attachmentName) !== null ? 1 : 0;
    }

    public function queueMailLog(string $recipientEmail, string $recipientName, string $subject, string $message, ?string $attachmentPath = null, ?string $attachmentName = null): ?int
    {
        $this->ensureActionColumns();

        $channel = $this->mailChannel();
        if (!$channel) {
            return null;
        }

        $recipientEmail = trim($recipientEmail);
        $missingAddress = $recipientEmail === '';

        $stmt = Database::connection()->prepare(
            'INSERT INTO notification_logs (
                rule_id,
                channel_id,
                visit_id,
                recipient_name,
                recipient_address,
                subject,
                message,
                action_yes_url,
                action_no_url,
                attachment_path,
                attachment_name,
                status,
                error_message
             ) VALUES (
                NULL,
                :channel_id,
                NULL,
                :recipient_name,
                :recipient_address,
                :subject,
                :message,
                NULL,
                NULL,
                :attachment_path,
                :attachment_name,
                :status,
                :error_message
             )'
        );
        $stmt->execute([
            'channel_id' => (int) $channel['id'],
            'recipient_name' => $recipientName !== '' ? $recipientName : null,
            'recipient_address' => $missingAddress ? '-' : $recipientEmail,
            'subject' => $subject,
            'message' => $message,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'status' => $missingAddress ? 'skipped' : 'queued',
            'error_message' => $missingAddress ? 'Mail için alıcı adresi eksik.' : null,
        ]);

        return $missingAddress ? null : (int) Database::connection()->lastInsertId();
    }

    private function visit(int $visitId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                v.*,
                vi.full_name AS visitor_name,
                vi.phone AS visitor_phone,
                vi.company AS visitor_company,
                vi.vehicle_plate,
                vc.name AS category_name,
                vc.code AS category_code,
                vc.is_notification_enabled AS category_notification_enabled,
                d.name AS department_name,
                d.code AS department_code,
                d.email AS department_email,
                d.phone AS department_phone
             FROM visits v
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = v.department_id
             WHERE v.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $visitId]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        return $visit ?: null;
    }

    private function rulesFor(string $eventType, int $categoryId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM notification_rules
             WHERE event_type = :event_type
               AND is_active = 1
               AND deleted_at IS NULL
               AND (category_id IS NULL OR category_id = :category_id)
             ORDER BY priority ASC, id ASC'
        );
        $stmt->execute([
            'event_type' => $eventType,
            'category_id' => $categoryId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function channelsForRule(int $ruleId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT nc.*
             FROM notification_rule_channels nrc
             INNER JOIN notification_channels nc ON nc.id = nrc.channel_id
             WHERE nrc.rule_id = :rule_id
               AND nc.is_enabled = 1
             ORDER BY nc.name'
        );
        $stmt->execute(['rule_id' => $ruleId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function enabledChannels(array|string|null $codes = null): array
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM notification_channels
             WHERE is_enabled = 1
             ORDER BY name'
        );

        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($codes === null) {
            return $channels;
        }

        $allowed = array_flip(array_filter(array_map('strval', (array) $codes)));

        return array_values(array_filter(
            $channels,
            fn (array $channel): bool => isset($allowed[(string) $channel['code']])
        ));
    }

    private function mailChannel(): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT *
             FROM notification_channels
             WHERE code = 'mail'
             LIMIT 1"
        );
        $stmt->execute();
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);

        return $channel ?: null;
    }

    private function recipientsForRule(int $ruleId, array $visit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM notification_rule_recipients
             WHERE rule_id = :rule_id
             ORDER BY id'
        );
        $stmt->execute(['rule_id' => $ruleId]);
        $definitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $recipients = [];

        foreach ($definitions as $definition) {
            $resolved = match ($definition['recipient_type']) {
                'user' => $this->userRecipients((int) $definition['user_id']),
                'role' => $this->roleRecipients((int) $definition['role_id']),
                'department_manager' => $this->departmentManagerRecipients(
                    (int) ($definition['department_id'] ?: $visit['department_id'] ?: 0)
                ),
                'contact' => $this->contactRecipients((int) $definition['contact_id']),
                default => [$this->customRecipient($definition)],
            };

            foreach ($resolved as $recipient) {
                if ($recipient) {
                    $recipients[] = $recipient;
                }
            }
        }

        return $this->uniqueRecipients($recipients);
    }

    private function userRecipients(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            "SELECT full_name, email, phone
             FROM users
             WHERE id = :id
               AND status = 'active'
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ? [$this->recipientFromUser($user)] : [];
    }

    private function roleRecipients(int $roleId): array
    {
        if ($roleId <= 0) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT u.full_name, u.email, u.phone
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             WHERE ur.role_id = :role_id
               AND u.status = 'active'
               AND u.deleted_at IS NULL
             ORDER BY u.full_name"
        );
        $stmt->execute(['role_id' => $roleId]);

        return array_map(fn (array $user): array => $this->recipientFromUser($user), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function departmentManagerRecipients(int $departmentId): array
    {
        if ($departmentId <= 0) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT
                d.name AS department_name,
                d.email AS department_email,
                d.phone AS department_phone,
                u.full_name,
                u.email,
                u.phone
             FROM departments d
             LEFT JOIN users u ON u.id = d.manager_user_id
                AND u.status = \'active\'
                AND u.deleted_at IS NULL
             WHERE d.id = :department_id
               AND d.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['department_id' => $departmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [];
        }

        if (!empty($row['full_name'])) {
            return [$this->recipientFromUser($row)];
        }

        return [[
            'name' => $row['department_name'] . ' Departmanı',
            'email' => $row['department_email'] ?? null,
            'phone' => $row['department_phone'] ?? null,
            'telegram_chat_id' => null,
            'whatsapp_number' => null,
        ]];
    }

    private function contactRecipients(int $contactId): array
    {
        if ($contactId <= 0) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT full_name, email, phone, telegram_chat_id, whatsapp_number
             FROM notification_contacts
             WHERE id = :id
               AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $contactId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contact) {
            return [];
        }

        return [[
            'name' => $contact['full_name'],
            'email' => $contact['email'],
            'phone' => $contact['phone'],
            'telegram_chat_id' => $contact['telegram_chat_id'],
            'whatsapp_number' => $contact['whatsapp_number'],
        ]];
    }

    private function customRecipient(array $definition): array
    {
        return [
            'name' => $definition['custom_name'] ?: 'Özel alıcı',
            'email' => $definition['custom_email'] ?: null,
            'phone' => $definition['custom_phone'] ?: null,
            'telegram_chat_id' => $definition['custom_telegram_chat_id'] ?: null,
            'whatsapp_number' => $definition['custom_whatsapp_number'] ?: null,
        ];
    }

    private function recipientFromUser(array $user): array
    {
        return [
            'name' => $user['full_name'],
            'email' => $user['email'] ?? null,
            'phone' => $user['phone'] ?? null,
            'telegram_chat_id' => null,
            'whatsapp_number' => $user['phone'] ?? null,
        ];
    }

    private function uniqueRecipients(array $recipients): array
    {
        $seen = [];
        $unique = [];

        foreach ($recipients as $recipient) {
            $key = implode('|', [
                $recipient['name'] ?? '',
                $recipient['email'] ?? '',
                $recipient['phone'] ?? '',
                $recipient['telegram_chat_id'] ?? '',
                $recipient['whatsapp_number'] ?? '',
            ]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $recipient;
        }

        return $unique;
    }

    private function createLog(array $rule, array $channel, array $visit, array $recipient, array $extra): void
    {
        $this->ensureActionColumns();

        $address = $this->recipientAddress((string) $channel['code'], $recipient);
        $missingAddress = trim($address) === '';
        $message = $this->renderTemplate($rule['message_template'] ?: $this->defaultTemplate($rule), $visit, $extra);
        $subject = 'Otel Güvenlik: ' . $rule['name'];
        $mail = $this->mailOutput((string) $channel['code'], (string) $rule['event_type'], $subject, $message, $visit, $extra);

        $stmt = Database::connection()->prepare(
            'INSERT INTO notification_logs (
                rule_id,
                channel_id,
                visit_id,
                recipient_name,
                recipient_address,
                subject,
                message,
                action_yes_url,
                action_no_url,
                attachment_path,
                attachment_name,
                status,
                error_message
             ) VALUES (
                :rule_id,
                :channel_id,
                :visit_id,
                :recipient_name,
                :recipient_address,
                :subject,
                :message,
                :action_yes_url,
                :action_no_url,
                :attachment_path,
                :attachment_name,
                :status,
                :error_message
             )'
        );
        $stmt->execute([
            'rule_id' => (int) $rule['id'],
            'channel_id' => (int) $channel['id'],
            'visit_id' => (int) $visit['id'],
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_address' => $missingAddress ? '-' : $address,
            'subject' => $mail['subject'],
            'message' => $mail['message'],
            'action_yes_url' => $this->actionUrl($extra, 'action_yes_url'),
            'action_no_url' => $this->actionUrl($extra, 'action_no_url'),
            'attachment_path' => $this->attachmentPath($extra),
            'attachment_name' => $this->attachmentName($extra),
            'status' => $missingAddress ? 'skipped' : 'queued',
            'error_message' => $missingAddress ? $channel['name'] . ' için alıcı adresi eksik.' : null,
        ]);
    }

    private function createSystemLog(array $rule, array $channel, array $recipient, array $context, array $payload, string $message): void
    {
        $this->ensureActionColumns();

        $address = $this->recipientAddress((string) $channel['code'], $recipient);
        $missingAddress = trim($address) === '';
        $subject = 'Otel Güvenlik: ' . $rule['name'];
        $mail = $this->mailOutput((string) $channel['code'], (string) $rule['event_type'], $subject, $message, $context, $payload);

        $stmt = Database::connection()->prepare(
            'INSERT INTO notification_logs (
                rule_id,
                channel_id,
                visit_id,
                recipient_name,
                recipient_address,
                subject,
                message,
                action_yes_url,
                action_no_url,
                attachment_path,
                attachment_name,
                status,
                error_message
             ) VALUES (
                :rule_id,
                :channel_id,
                NULL,
                :recipient_name,
                :recipient_address,
                :subject,
                :message,
                NULL,
                NULL,
                :attachment_path,
                :attachment_name,
                :status,
                :error_message
             )'
        );
        $stmt->execute([
            'rule_id' => (int) $rule['id'],
            'channel_id' => (int) $channel['id'],
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_address' => $missingAddress ? '-' : $address,
            'subject' => $mail['subject'],
            'message' => $mail['message'],
            'attachment_path' => $this->attachmentPath($payload),
            'attachment_name' => $this->attachmentName($payload),
            'status' => $missingAddress ? 'skipped' : 'queued',
            'error_message' => $missingAddress ? $channel['name'] . ' için alıcı adresi eksik.' : null,
        ]);
    }

    private function createDirectLog(array $channel, ?array $visit, array $recipient, string $subject, string $message, array $extra = []): void
    {
        $this->ensureActionColumns();

        $address = $this->recipientAddress((string) $channel['code'], $recipient);
        $missingAddress = trim($address) === '';
        $eventType = (string) ($extra['mail_template_event'] ?? 'escalation');
        $mail = $this->mailOutput((string) $channel['code'], $eventType, $subject, $message, $visit ?? [], $extra);

        $stmt = Database::connection()->prepare(
            'INSERT INTO notification_logs (
                rule_id,
                channel_id,
                visit_id,
                recipient_name,
                recipient_address,
                subject,
                message,
                action_yes_url,
                action_no_url,
                attachment_path,
                attachment_name,
                status,
                error_message
             ) VALUES (
                NULL,
                :channel_id,
                :visit_id,
                :recipient_name,
                :recipient_address,
                :subject,
                :message,
                :action_yes_url,
                :action_no_url,
                :attachment_path,
                :attachment_name,
                :status,
                :error_message
             )'
        );
        $stmt->execute([
            'channel_id' => (int) $channel['id'],
            'visit_id' => isset($visit['id']) ? (int) $visit['id'] : null,
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_address' => $missingAddress ? '-' : $address,
            'subject' => $mail['subject'],
            'message' => $mail['message'],
            'action_yes_url' => $this->actionUrl($extra, 'action_yes_url'),
            'action_no_url' => $this->actionUrl($extra, 'action_no_url'),
            'attachment_path' => $this->attachmentPath($extra),
            'attachment_name' => $this->attachmentName($extra),
            'status' => $missingAddress ? 'skipped' : 'queued',
            'error_message' => $missingAddress ? $channel['name'] . ' için alıcı adresi eksik.' : null,
        ]);
    }

    private function mailOutput(string $channelCode, string $eventType, string $defaultSubject, string $defaultMessage, array $context, array $extra): array
    {
        if ($channelCode !== 'mail' || !isset(MailTemplate::EVENTS[$eventType])) {
            return [
                'subject' => $defaultSubject,
                'message' => $defaultMessage,
            ];
        }

        $template = (new MailTemplate())->forEvent($eventType);
        $values = $extra + [
            'subject' => $defaultSubject,
            'message' => $defaultMessage,
        ];

        $body = $this->renderTemplate($template['body'], $context, $values);
        $css = trim($this->renderTemplate((string) ($template['css'] ?? ''), $context, $values));

        return [
            'subject' => $this->renderTemplate($template['subject'], $context, $values),
            'message' => $css !== '' ? '<style>' . $css . '</style>' . $body : $body,
        ];
    }

    private function actionUrl(array $extra, string $key): ?string
    {
        $value = trim((string) ($extra[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function attachmentPath(array $extra): ?string
    {
        $path = trim((string) ($extra['attachment_path'] ?? ''));

        return $path !== '' ? $path : null;
    }

    private function attachmentName(array $extra): ?string
    {
        $name = trim((string) ($extra['attachment_name'] ?? ''));

        return $name !== '' ? $name : null;
    }

    private function ensureActionColumns(): void
    {
        if (self::$actionColumnsEnsured) {
            return;
        }

        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM notification_logs')->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('action_yes_url', $columns, true)) {
            $pdo->exec('ALTER TABLE notification_logs ADD COLUMN action_yes_url VARCHAR(500) NULL AFTER message');
        }

        if (!in_array('action_no_url', $columns, true)) {
            $pdo->exec('ALTER TABLE notification_logs ADD COLUMN action_no_url VARCHAR(500) NULL AFTER action_yes_url');
        }

        if (!in_array('attachment_path', $columns, true)) {
            $pdo->exec('ALTER TABLE notification_logs ADD COLUMN attachment_path VARCHAR(500) NULL AFTER action_no_url');
        }

        if (!in_array('attachment_name', $columns, true)) {
            $pdo->exec('ALTER TABLE notification_logs ADD COLUMN attachment_name VARCHAR(180) NULL AFTER attachment_path');
        }

        self::$actionColumnsEnsured = true;
    }

    private function recipientAddress(string $channelCode, array $recipient): string
    {
        return match ($channelCode) {
            'mail' => (string) ($recipient['email'] ?? ''),
            'telegram' => (string) ($recipient['telegram_chat_id'] ?? ''),
            'whatsapp' => (string) (($recipient['whatsapp_number'] ?? '') ?: ($recipient['phone'] ?? '')),
            default => '',
        };
    }

    private function defaultTemplate(array $rule): string
    {
        return match ($rule['event_type']) {
            'exit' => '{visitor_name} çıkış yaptı. Departman: {department_name}. Çıkış saati: {exit_at}.',
            'timeout_warning' => '{visitor_name} için içeride kalma süresi yaklaşıyor. Departman: {department_name}.',
            'department_question' => '{visitor_name} halen sizinle beraber mi? Departman: {department_name}.',
            'department_answer_no' => '{visitor_name} departman tarafından onaylanmadı. Yönetici kontrolü gerekli.',
            'department_no_response' => '{visitor_name} için departmandan cevap alınamadı. Yönetici kontrolü gerekli.',
            default => '{visitor_name} giriş yaptı. Kategori: {category_name}. Departman: {department_name}. Giriş saati: {entry_at}.',
        };
    }

    private function defaultSystemTemplate(string $eventType): string
    {
        return match ($eventType) {
            'daily_report' => '{report_name} hazırlandı. Dönem: {period_start} - {period_end}. Özet: {report_summary}. Dosya: {file_path}',
            'weekly_report' => '{report_name} hazırlandı. Dönem: {period_start} - {period_end}. Özet: {report_summary}. Dosya: {file_path}',
            'monthly_report' => '{report_name} hazırlandı. Dönem: {period_start} - {period_end}. Özet: {report_summary}. Dosya: {file_path}',
            default => '{message}',
        };
    }

    private function renderTemplate(string $template, array $visit, array $extra): string
    {
        $values = [
            'visitor_name' => $visit['visitor_name'] ?? '-',
            'visitor_phone' => $visit['visitor_phone'] ?? '-',
            'company' => $visit['visitor_company'] ?? '-',
            'vehicle_plate' => $visit['vehicle_plate'] ?? '-',
            'category_name' => $visit['category_name'] ?? '-',
            'category_code' => $visit['category_code'] ?? '-',
            'department_name' => $visit['department_name'] ?? 'Departman seçilmedi',
            'department_code' => $visit['department_code'] ?? '-',
            'host_name' => $visit['host_name'] ?? '-',
            'purpose' => $visit['purpose'] ?? '-',
            'entry_at' => $this->formatDate($visit['entry_at'] ?? null),
            'exit_at' => $this->formatDate($visit['exit_at'] ?? null),
            'elapsed_minutes' => (string) $this->elapsedMinutes($visit),
        ];

        foreach ($extra as $key => $value) {
            if (is_scalar($value)) {
                $values[(string) $key] = (string) $value;
            }
        }

        $replace = [];
        foreach ($values as $key => $value) {
            $replace['{' . $key . '}'] = (string) $value;
        }

        return strtr($template, $replace);
    }

    private function passesConditions(?string $conditionJson, array $visit, array $extra): bool
    {
        if (!$conditionJson) {
            return true;
        }

        $conditions = json_decode($conditionJson, true);
        if (!is_array($conditions)) {
            return true;
        }

        foreach ($conditions as $key => $value) {
            $actual = match ($key) {
                'department_id' => $visit['department_id'] ?? null,
                'department_code' => $visit['department_code'] ?? null,
                'status' => $visit['status'] ?? null,
                'min_elapsed_minutes' => $this->elapsedMinutes($visit),
                'max_elapsed_minutes' => $this->elapsedMinutes($visit),
                default => $extra[$key] ?? null,
            };

            if ($key === 'min_elapsed_minutes' && $actual < (int) $value) {
                return false;
            }

            if ($key === 'max_elapsed_minutes' && $actual > (int) $value) {
                return false;
            }

            if (!in_array($key, ['min_elapsed_minutes', 'max_elapsed_minutes'], true) && (string) $actual !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function elapsedMinutes(array $visit): int
    {
        $entryAt = strtotime((string) ($visit['entry_at'] ?? ''));
        $endAt = !empty($visit['exit_at']) ? strtotime((string) $visit['exit_at']) : time();

        if (!$entryAt || !$endAt || $endAt < $entryAt) {
            return 0;
        }

        return (int) floor(($endAt - $entryAt) / 60);
    }

    private function formatDate(?string $value): string
    {
        if (!$value) {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('d.m.Y H:i', $timestamp) : '-';
    }
}
