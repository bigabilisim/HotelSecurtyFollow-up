<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Settings;
use Throwable;

final class PwaController
{
    public function manifest(): string
    {
        $settings = $this->settings();
        $appName = $settings['app.name'] ?? config('app.name', 'Otel Güvenlik Sistemi');
        $shortName = function_exists('mb_substr') ? mb_substr($appName, 0, 18, 'UTF-8') : substr($appName, 0, 18);

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-store');

        return json_encode([
            'name' => $appName,
            'short_name' => $shortName,
            'description' => 'Otel güvenlik giriş çıkış takip paneli',
            'id' => route('/dashboard'),
            'start_url' => route('/dashboard'),
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone', 'browser'],
            'orientation' => 'portrait-primary',
            'background_color' => '#f4f5ef',
            'theme_color' => '#21302b',
            'categories' => ['business', 'productivity', 'utilities'],
            'icons' => [
                [
                    'src' => '/icons/icon.svg',
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => '/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => '/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function settings(): array
    {
        try {
            return (new Settings())->all();
        } catch (Throwable) {
            return [];
        }
    }
}
