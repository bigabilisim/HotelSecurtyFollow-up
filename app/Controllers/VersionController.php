<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Settings;

final class VersionController
{
    public function history(): string
    {
        $historyPath = BASE_PATH . '/docs/version-gecmisi.md';
        $historyText = is_file($historyPath)
            ? (string) file_get_contents($historyPath)
            : $this->fallbackHistory(config('versions', []));

        return view('version/history', [
            'title' => 'Sürüm Geçmişi',
            'settings' => (new Settings())->all(),
            'historyText' => $historyText,
        ]);
    }

    private function fallbackHistory(array $versionInfo): string
    {
        $entries = is_array($versionInfo['entries'] ?? null) ? $versionInfo['entries'] : [];
        $lines = ['# Sürüm Geçmişi', ''];

        foreach ($entries as $entry) {
            $version = (string) ($entry['version'] ?? 'Sürüm');
            $publishedAt = (string) ($entry['published_at'] ?? '');
            $date = $publishedAt !== '' ? 'Yayın: ' . $publishedAt : 'Test';
            $lines[] = '## ' . $version . ' - ' . $date;
            $lines[] = '';
            $lines[] = (string) ($entry['title'] ?? '');
            $lines[] = '';
            $lines[] = (string) ($entry['summary'] ?? '');
            $lines[] = '';

            foreach (($entry['items'] ?? []) as $item) {
                $lines[] = '- ' . (string) $item;
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
