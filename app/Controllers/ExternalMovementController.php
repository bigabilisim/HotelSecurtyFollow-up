<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Department;
use App\Models\ExternalMovement;
use App\Models\MobileNotification;
use App\Models\Settings;
use App\Services\WebPushService;

final class ExternalMovementController
{
    public function index(): string
    {
        Auth::requireLogin();
        $this->ensureEnabled();

        $movementModel = new ExternalMovement();

        return view('external-movements/index', [
            'title' => 'Dış Görev',
            'settings' => (new Settings())->all(),
            'departments' => (new Department())->all(),
            'outsideMovements' => $movementModel->outside(),
            'recentMovements' => $movementModel->recentReturned(),
            'quickNotes' => $movementModel->quickNotes(),
        ]);
    }

    public function exit(): never
    {
        Auth::requireLogin();
        $this->ensureEnabled();
        $this->verifyCsrf();

        $personName = trim((string) ($_POST['person_name'] ?? ''));
        if ($personName === '') {
            flash('error', 'Dış görev çıkışı için kişi adı zorunludur.');
            redirect('/external-movements');
        }

        $movementId = (new ExternalMovement())->createExit([
            'department_id' => (int) ($_POST['department_id'] ?? 0),
            'person_name' => $personName,
            'vehicle_plate' => $this->formatVehiclePlate(trim((string) ($_POST['vehicle_plate'] ?? ''))),
            'exit_km' => $_POST['exit_km'] ?? null,
            'destination_note' => trim((string) ($_POST['destination_note'] ?? '')),
        ], (int) Auth::id());

        $this->queueExitNotification($movementId);

        flash('success', 'Dış görev çıkışı kaydedildi.');
        redirect('/external-movements');
    }

    public function returnEntry(): never
    {
        Auth::requireLogin();
        $this->ensureEnabled();
        $this->verifyCsrf();

        $movementId = (int) ($_POST['movement_id'] ?? 0);
        if ($movementId <= 0) {
            flash('error', 'Giriş verilecek dış görev kaydı seçilemedi.');
            redirect('/external-movements');
        }

        $returned = (new ExternalMovement())->createReturn($movementId, [
            'return_km' => $_POST['return_km'] ?? null,
            'return_note' => trim((string) ($_POST['return_note'] ?? '')),
        ], (int) Auth::id());

        flash($returned ? 'success' : 'error', $returned ? 'Dış görev girişi kaydedildi.' : 'Giriş kaydedilemedi. Dönüş km, çıkış km değerinden küçük olmamalı.');
        redirect('/external-movements');
    }

    private function ensureEnabled(): void
    {
        $settings = (new Settings())->all();
        if ((string) ($settings['external_movements.enabled'] ?? '0') !== '1') {
            flash('error', 'Dış görev takibi şu anda kapalı. Yönetim > Sistem ayarlarından açılmalıdır.');
            redirect('/dashboard');
        }
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/external-movements');
        }
    }

    private function queueExitNotification(int $movementId): void
    {
        try {
            $result = (new MobileNotification())->queueExternalMovementExit($movementId);
            $logIds = array_map('intval', $result['log_ids'] ?? []);

            if ($logIds) {
                (new WebPushService())->sendQueuedLogIds($logIds);
            }
        } catch (\Throwable $error) {
            error_log('External movement exit notification error: ' . $error->getMessage());
        }
    }

    private function formatVehiclePlate(string $value): string
    {
        $normalized = strtr($value, [
            'ç' => 'C',
            'Ç' => 'C',
            'ğ' => 'G',
            'Ğ' => 'G',
            'ı' => 'I',
            'İ' => 'I',
            'ö' => 'O',
            'Ö' => 'O',
            'ş' => 'S',
            'Ş' => 'S',
            'ü' => 'U',
            'Ü' => 'U',
        ]);
        $compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $normalized) ?? '');
        $length = strlen($compact);
        $index = 0;
        $province = '';
        $letters = '';
        $numbers = '';

        while ($index < $length && ctype_digit($compact[$index]) && strlen($province) < 2) {
            $province .= $compact[$index];
            $index++;
        }

        while ($index < $length && ctype_alpha($compact[$index]) && strlen($letters) < 3) {
            $letters .= $compact[$index];
            $index++;
        }

        while ($index < $length && ctype_digit($compact[$index]) && strlen($numbers) < 4) {
            $numbers .= $compact[$index];
            $index++;
        }

        return implode(' ', array_filter([$province, $letters, $numbers], static fn (string $part): bool => $part !== ''));
    }
}
