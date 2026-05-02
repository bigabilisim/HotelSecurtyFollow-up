<?php

declare(strict_types=1);

namespace App\Models;

final class MailTemplate
{
    public const EVENTS = [
        'entry' => 'Giriş Bildirimi',
        'exit' => 'Çıkış Bildirimi',
        'timeout_warning' => 'Süre Uyarısı',
        'department_question' => 'Departman Sorusu',
        'department_answer_no' => 'Departman Hayır Cevabı',
        'department_no_response' => 'Cevapsız Eskalasyon',
        'escalation' => 'N+ Eskalasyon',
        'daily_report' => 'Gün Sonu Raporu',
        'weekly_report' => 'Haftalık Rapor',
        'monthly_report' => 'Ay Sonu Raporu',
    ];

    public function all(): array
    {
        $settings = (new Settings())->all();
        $templates = [];

        foreach (self::EVENTS as $eventType => $label) {
            $default = $this->defaultFor($eventType);
            $templates[$eventType] = [
                'event_type' => $eventType,
                'label' => $label,
                'subject' => $settings[$this->subjectKey($eventType)] ?? $default['subject'],
                'body' => $settings[$this->bodyKey($eventType)] ?? $default['body'],
            ];
        }

        return $templates;
    }

    public function forEvent(string $eventType): array
    {
        $settings = (new Settings())->all();
        $default = $this->defaultFor($eventType);

        return [
            'subject' => $settings[$this->subjectKey($eventType)] ?? $default['subject'],
            'body' => $settings[$this->bodyKey($eventType)] ?? $default['body'],
        ];
    }

    public function save(array $data): void
    {
        $settings = [];

        foreach (array_keys(self::EVENTS) as $eventType) {
            $default = $this->defaultFor($eventType);
            $subject = trim((string) ($data[$eventType]['subject'] ?? ''));
            $body = trim((string) ($data[$eventType]['body'] ?? ''));

            $settings[$this->subjectKey($eventType)] = $subject !== '' ? $subject : $default['subject'];
            $settings[$this->bodyKey($eventType)] = $body !== '' ? $body : $default['body'];
        }

        (new Settings())->setMany($settings);
    }

    private function defaultFor(string $eventType): array
    {
        return match ($eventType) {
            'exit' => [
                'subject' => 'Otel Güvenlik: {visitor_name} çıkış yaptı',
                'body' => "Ziyaretçi: {visitor_name}\nDepartman: {department_name}\nÇıkış saati: {exit_at}",
            ],
            'timeout_warning' => [
                'subject' => 'Otel Güvenlik: süre yaklaşıyor - {visitor_name}',
                'body' => "Ziyaretçi: {visitor_name}\nDepartman: {department_name}\nİçeride kalma süresi yaklaşıyor.\nGeçen süre: {elapsed_minutes} dk",
            ],
            'department_question' => [
                'subject' => 'Otel Güvenlik: departman doğrulaması - {visitor_name}',
                'body' => "{question_text}\n\nZiyaretçi: {visitor_name}\nDepartman: {department_name}\nGiriş saati: {entry_at}",
            ],
            'department_answer_no' => [
                'subject' => 'Otel Güvenlik: olumsuz departman cevabı - {visitor_name}',
                'body' => "Departman cevabı olumsuz.\nZiyaretçi: {visitor_name}\nDepartman: {department_name}\nGiriş saati: {entry_at}",
            ],
            'department_no_response' => [
                'subject' => 'Otel Güvenlik: cevap alınamadı - {visitor_name}',
                'body' => "Departman cevabı alınamadı.\nZiyaretçi: {visitor_name}\nDepartman: {department_name}\nGiriş saati: {entry_at}",
            ],
            'escalation' => [
                'subject' => '{subject}',
                'body' => "{message}\n\nZiyaretçi: {visitor_name}\nDepartman: {department_name}\nGiriş saati: {entry_at}",
            ],
            'daily_report' => [
                'subject' => 'Otel Güvenlik: gün sonu raporu',
                'body' => "{report_name} hazırlandı.\nDönem: {period_start} - {period_end}\nÖzet: {report_summary}\nDosya: {file_path}",
            ],
            'monthly_report' => [
                'subject' => 'Otel Güvenlik: ay sonu raporu',
                'body' => "{report_name} hazırlandı.\nDönem: {period_start} - {period_end}\nÖzet: {report_summary}\nDosya: {file_path}",
            ],
            'weekly_report' => [
                'subject' => 'Otel Güvenlik: haftalık rapor',
                'body' => "{report_name} hazırlandı.\nDönem: {period_start} - {period_end}\nÖzet: {report_summary}\nDosya: {file_path}",
            ],
            default => [
                'subject' => 'Otel Güvenlik: {visitor_name} giriş yaptı',
                'body' => "Ziyaretçi: {visitor_name}\nKategori: {category_name}\nDepartman: {department_name}\nGiriş saati: {entry_at}",
            ],
        };
    }

    private function subjectKey(string $eventType): string
    {
        return 'mail_template.' . $eventType . '.subject';
    }

    private function bodyKey(string $eventType): string
    {
        return 'mail_template.' . $eventType . '.body';
    }
}
