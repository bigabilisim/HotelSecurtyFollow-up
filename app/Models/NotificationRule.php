<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class NotificationRule
{
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT
                nr.id,
                nr.category_id,
                nr.name,
                nr.event_type,
                nr.priority,
                nr.condition_json,
                nr.message_template,
                nr.is_active,
                nr.created_at,
                nr.updated_at,
                vc.name AS category_name,
                GROUP_CONCAT(DISTINCT nc.name ORDER BY nc.name SEPARATOR ", ") AS channel_names,
                GROUP_CONCAT(DISTINCT COALESCE(nrr.custom_name, nco.full_name, r.name, u.full_name, d.name) ORDER BY nrr.id SEPARATOR ", ") AS recipient_names
             FROM notification_rules nr
             LEFT JOIN visitor_categories vc ON vc.id = nr.category_id
             LEFT JOIN notification_rule_channels nrc ON nrc.rule_id = nr.id
             LEFT JOIN notification_channels nc ON nc.id = nrc.channel_id
             LEFT JOIN notification_rule_recipients nrr ON nrr.rule_id = nr.id
             LEFT JOIN notification_contacts nco ON nco.id = nrr.contact_id
             LEFT JOIN roles r ON r.id = nrr.role_id
             LEFT JOIN users u ON u.id = nrr.user_id
             LEFT JOIN departments d ON d.id = nrr.department_id
             WHERE nr.deleted_at IS NULL
             GROUP BY
                nr.id,
                nr.category_id,
                nr.name,
                nr.event_type,
                nr.priority,
                nr.condition_json,
                nr.message_template,
                nr.is_active,
                nr.created_at,
                nr.updated_at,
                vc.name
             ORDER BY nr.is_active DESC, nr.priority ASC, nr.name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function channels(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, code, name
             FROM notification_channels
             WHERE is_enabled = 1
             ORDER BY name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findForEdit(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM notification_rules
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            return null;
        }

        $channelStmt = Database::connection()->prepare(
            'SELECT channel_id
             FROM notification_rule_channels
             WHERE rule_id = :rule_id'
        );
        $channelStmt->execute(['rule_id' => $id]);
        $rule['channel_ids'] = array_map('intval', $channelStmt->fetchAll(PDO::FETCH_COLUMN));

        $recipientStmt = Database::connection()->prepare(
            'SELECT *
             FROM notification_rule_recipients
             WHERE rule_id = :rule_id
             ORDER BY id
             LIMIT 1'
        );
        $recipientStmt->execute(['rule_id' => $id]);
        $recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $rule['recipient_type'] = $recipient['recipient_type'] ?? 'custom';
        $rule['recipient_department_id'] = $recipient['department_id'] ?? null;
        $rule['recipient_role_id'] = $recipient['role_id'] ?? null;
        $rule['custom_name'] = $recipient['custom_name'] ?? null;
        $rule['custom_email'] = $recipient['custom_email'] ?? null;
        $rule['custom_phone'] = $recipient['custom_phone'] ?? null;
        $rule['custom_telegram_chat_id'] = $recipient['custom_telegram_chat_id'] ?? null;
        $rule['custom_whatsapp_number'] = $recipient['custom_whatsapp_number'] ?? null;

        return $rule;
    }

    public function create(array $data): void
    {
        $this->save($data);
    }

    public function save(array $data): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                !empty($data['id'])
                    ? 'UPDATE notification_rules
                       SET category_id = :category_id,
                           name = :name,
                           event_type = :event_type,
                           priority = :priority,
                           condition_json = :condition_json,
                           message_template = :message_template,
                           is_active = :is_active
                       WHERE id = :id AND deleted_at IS NULL'
                    : 'INSERT INTO notification_rules (
                    category_id,
                    name,
                    event_type,
                    priority,
                    condition_json,
                    message_template,
                    is_active
                 ) VALUES (
                    :category_id,
                    :name,
                    :event_type,
                    :priority,
                    :condition_json,
                    :message_template,
                    :is_active
                 )
                 ON DUPLICATE KEY UPDATE
                    priority = VALUES(priority),
                    condition_json = VALUES(condition_json),
                    message_template = VALUES(message_template),
                    is_active = VALUES(is_active),
                    deleted_at = NULL'
            );

            $payload = [
                'category_id' => $data['category_id'] ?: null,
                'name' => $data['name'],
                'event_type' => $data['event_type'],
                'priority' => $data['priority'] ?: 100,
                'condition_json' => $data['condition_json'] ?: null,
                'message_template' => $data['message_template'] ?: null,
                'is_active' => !empty($data['is_active']) ? 1 : 0,
            ];

            if (!empty($data['id'])) {
                $payload['id'] = (int) $data['id'];
            }

            $stmt->execute($payload);

            $ruleId = (int) ($data['id'] ?? 0);

            if ($ruleId === 0) {
                $ruleId = (int) $pdo->lastInsertId();
            }

            if ($ruleId === 0) {
                $find = $pdo->prepare(
                    'SELECT id
                     FROM notification_rules
                     WHERE name = :name AND event_type = :event_type AND (category_id <=> :category_id)
                     LIMIT 1'
                );
                $find->execute([
                    'name' => $data['name'],
                    'event_type' => $data['event_type'],
                    'category_id' => $data['category_id'] ?: null,
                ]);
                $ruleId = (int) $find->fetchColumn();
            }

            $pdo->prepare('DELETE FROM notification_rule_channels WHERE rule_id = :rule_id')
                ->execute(['rule_id' => $ruleId]);
            $pdo->prepare('DELETE FROM notification_rule_recipients WHERE rule_id = :rule_id')
                ->execute(['rule_id' => $ruleId]);

            $channelStmt = $pdo->prepare(
                'INSERT INTO notification_rule_channels (rule_id, channel_id)
                 VALUES (:rule_id, :channel_id)'
            );

            foreach ($data['channel_ids'] as $channelId) {
                $channelStmt->execute([
                    'rule_id' => $ruleId,
                    'channel_id' => (int) $channelId,
                ]);
            }

            if (($data['recipient_type'] ?? '') === 'department_manager') {
                $this->insertRecipient($ruleId, 'department_manager', [
                    'department_id' => $data['recipient_department_id'] ?: null,
                ]);
            } elseif (($data['recipient_type'] ?? '') === 'role') {
                $this->insertRecipient($ruleId, 'role', [
                    'role_id' => $data['recipient_role_id'] ?: null,
                ]);
            } else {
                $this->insertRecipient($ruleId, 'custom', [
                    'custom_name' => $data['custom_name'] ?: null,
                    'custom_email' => $data['custom_email'] ?: null,
                    'custom_phone' => $data['custom_phone'] ?: null,
                    'custom_telegram_chat_id' => $data['custom_telegram_chat_id'] ?: null,
                    'custom_whatsapp_number' => $data['custom_whatsapp_number'] ?: null,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notification_rules
             SET deleted_at = NOW(), is_active = 0
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }

    private function insertRecipient(int $ruleId, string $type, array $values): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notification_rule_recipients (
                rule_id,
                recipient_type,
                role_id,
                department_id,
                custom_name,
                custom_email,
                custom_phone,
                custom_telegram_chat_id,
                custom_whatsapp_number
             ) VALUES (
                :rule_id,
                :recipient_type,
                :role_id,
                :department_id,
                :custom_name,
                :custom_email,
                :custom_phone,
                :custom_telegram_chat_id,
                :custom_whatsapp_number
             )'
        );

        $stmt->execute([
            'rule_id' => $ruleId,
            'recipient_type' => $type,
            'role_id' => $values['role_id'] ?? null,
            'department_id' => $values['department_id'] ?? null,
            'custom_name' => $values['custom_name'] ?? null,
            'custom_email' => $values['custom_email'] ?? null,
            'custom_phone' => $values['custom_phone'] ?? null,
            'custom_telegram_chat_id' => $values['custom_telegram_chat_id'] ?? null,
            'custom_whatsapp_number' => $values['custom_whatsapp_number'] ?? null,
        ]);
    }
}
