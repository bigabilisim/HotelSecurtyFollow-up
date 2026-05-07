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
            'vehicleKmData' => $movementModel->vehicleKmLookup(),
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

        $movementModel = new ExternalMovement();
        $vehiclePlate = $this->formatVehiclePlate(trim((string) ($_POST['vehicle_plate'] ?? '')));
        $exitKm = $this->positiveInt($_POST['exit_km'] ?? null);
        $previousKm = $vehiclePlate !== '' ? $movementModel->latestVehicleKm($vehiclePlate) : null;

        $movementId = $movementModel->createExit([
            'department_id' => (int) ($_POST['department_id'] ?? 0),
            'person_name' => $personName,
            'vehicle_plate' => $vehiclePlate,
            'exit_km' => $exitKm,
            'destination_note' => trim((string) ($_POST['destination_note'] ?? '')),
        ], (int) Auth::id());

        $this->queueExitNotification($movementId);

        $kmMessage = $this->vehicleKmFlashMessage($previousKm, $exitKm);
        flash('success', trim('Dış görev çıkışı kaydedildi. ' . $kmMessage));
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

    private function positiveInt(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : max(0, (int) $value);
    }

    private function vehicleKmFlashMessage(?array $previousKm, ?int $exitKm): string
    {
        if (!$previousKm || $exitKm === null) {
            return '';
        }

        $lastKm = (int) ($previousKm['last_km'] ?? 0);
        $difference = $exitKm - $lastKm;
        $lastKmText = number_format($lastKm, 0, ',', '.');

        if ($difference < 0) {
            return "Bilgi: Girilen km, aynı aracın son kaydı olan {$lastKmText} km değerinden düşük görünüyor.";
        }

        $differenceText = number_format($difference, 0, ',', '.');

        return "Bilgi: Aynı aracın son kaydı {$lastKmText} km idi; bu çıkışa göre fark {$differenceText} km.";
    }
}
