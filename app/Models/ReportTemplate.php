<?php

declare(strict_types=1);

namespace App\Models;

final class ReportTemplate
{
    public const TYPES = [
        'daily' => 'Günlük Rapor Tasarımı',
        'weekly' => 'Haftalık Rapor Tasarımı',
        'monthly' => 'Aylık Rapor Tasarımı',
    ];

    public function all(): array
    {
        $settings = (new Settings())->all();
        $templates = [];

        foreach (self::TYPES as $type => $label) {
            $templates[$type] = [
                'type' => $type,
                'label' => $label,
                'html' => $settings[$this->htmlKey($type)] ?? $this->defaultHtml($type),
                'css' => $settings[$this->cssKey($type)] ?? $this->defaultCss(),
            ];
        }

        return $templates;
    }

    public function forType(string $type): array
    {
        $type = array_key_exists($type, self::TYPES) ? $type : 'daily';
        $settings = (new Settings())->all();

        return [
            'type' => $type,
            'label' => self::TYPES[$type],
            'html' => $settings[$this->htmlKey($type)] ?? $this->defaultHtml($type),
            'css' => $settings[$this->cssKey($type)] ?? $this->defaultCss(),
        ];
    }

    public function save(array $data): void
    {
        $settings = [];

        foreach (array_keys(self::TYPES) as $type) {
            $html = trim((string) ($data[$type]['html'] ?? ''));
            $css = trim((string) ($data[$type]['css'] ?? ''));

            $settings[$this->htmlKey($type)] = $html !== '' ? $html : $this->defaultHtml($type);
            $settings[$this->cssKey($type)] = $css !== '' ? $css : $this->defaultCss();
        }

        (new Settings())->setMany($settings);
    }

    public function render(string $type, array $values): string
    {
        $template = $this->forType($type);
        return $this->renderCustom((string) $template['html'], (string) $template['css'], $values, $type);
    }

    public function renderCustom(string $html, string $css, array $values, string $type = 'daily'): string
    {
        $html = trim($html);
        $css = trim($css);

        if ($html === '') {
            $html = $this->defaultHtml($type);
        }

        if ($css === '') {
            $css = $this->defaultCss();
        }

        $replace = [];
        foreach ($values as $key => $value) {
            $replace['{' . $key . '}'] = (string) $value;
        }

        return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>'
            . (string) ($values['report_name'] ?? 'Rapor')
            . '</title><style>' . $css . '</style></head><body>'
            . strtr($html, $replace)
            . '</body></html>';
    }

    private function defaultHtml(string $type): string
    {
        $label = self::TYPES[$type] ?? self::TYPES['daily'];

        return '<section class="report-page">'
            . '<div class="report-hero">'
            . '<p class="eyebrow">' . $this->escape($label) . '</p>'
            . '<h1>{report_name}</h1>'
            . '<p>Dönem: {period_start} - {period_end}</p>'
            . '<small>Oluşturma: {generated_at}</small>'
            . '</div>'
            . '<section class="metric-grid">'
            . '<article><span>Giriş</span><strong>{total_entries}</strong></article>'
            . '<article><span>Çıkış</span><strong>{total_exits}</strong></article>'
            . '<article><span>İçeride</span><strong>{still_inside}</strong></article>'
            . '<article><span>Süre Aşımı</span><strong>{overdue_count}</strong></article>'
            . '</section>'
            . '<section class="report-columns">'
            . '<div><h2>Kategori Dağılımı</h2><table><thead><tr><th>Kategori</th><th>Adet</th></tr></thead><tbody>{category_rows}</tbody></table></div>'
            . '<div><h2>Departman Dağılımı</h2><table><thead><tr><th>Departman</th><th>Adet</th></tr></thead><tbody>{department_rows}</tbody></table></div>'
            . '</section>'
            . '</section>';
    }

    private function defaultCss(): string
    {
        return 'body{font-family:Arial,sans-serif;color:#18211f;margin:0;background:#f5f6f1}'
            . '.report-page{padding:28px;max-width:1120px;margin:0 auto}'
            . '.report-hero{background:#21302b;color:#fff;border-radius:12px;padding:24px;margin-bottom:18px}'
            . '.eyebrow{letter-spacing:2px;text-transform:uppercase;font-size:12px;font-weight:700;margin:0 0 8px;color:#d9efe8}'
            . 'h1{margin:0 0 8px;font-size:30px}h2{font-size:18px;margin:0 0 10px}'
            . '.metric-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}'
            . '.metric-grid article,.report-columns>div{background:#fff;border:1px solid #d9ded5;border-radius:10px;padding:16px}'
            . '.metric-grid span{display:block;color:#66706d;font-weight:700}.metric-grid strong{display:block;font-size:32px;margin-top:6px}'
            . '.report-columns{display:grid;grid-template-columns:1fr 1fr;gap:14px}'
            . 'table{border-collapse:collapse;width:100%;font-size:14px}th,td{border-bottom:1px solid #e2e6de;padding:9px;text-align:left}th{color:#66706d}'
            . '@media(max-width:760px){.metric-grid,.report-columns{grid-template-columns:1fr}.report-page{padding:16px}}';
    }

    private function htmlKey(string $type): string
    {
        return 'report_template.' . $type . '.html';
    }

    private function cssKey(string $type): string
    {
        return 'report_template.' . $type . '.css';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
