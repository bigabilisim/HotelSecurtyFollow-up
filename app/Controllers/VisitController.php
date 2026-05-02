<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\MobileNotification;
use App\Models\ReservationlessReview;
use App\Models\Visit;
use App\Models\Watchlist;
use App\Services\Notifications\NotificationService;
use App\Services\WebPushService;

final class VisitController
{
    public function entry(): never
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/dashboard');
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $vehiclePlate = $this->formatVehiclePlate(trim($_POST['vehicle_plate'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '' || $categoryId <= 0) {
            flash('error', 'Ad soyad ve kategori zorunludur.');
            redirect('/dashboard');
        }

        $watchMatches = (new Watchlist())->matches([
            'full_name' => $fullName,
            'phone' => $phone,
            'vehicle_plate' => $vehiclePlate,
        ]);
        $blocked = array_values(array_filter($watchMatches, static fn (array $match): bool => $match['list_type'] === 'blacklist'));

        if ($blocked) {
            $this->flashSecurityAlert('blacklist', $fullName, $blocked[0]);
            redirect('/dashboard');
        }

        $visitId = (new Visit())->createEntry([
            'full_name' => $fullName,
            'category_id' => $categoryId,
            'department_id' => (int) ($_POST['department_id'] ?? 0),
            'phone' => $phone,
            'company' => trim($_POST['company'] ?? ''),
            'vehicle_plate' => $vehiclePlate,
            'host_name' => trim($_POST['host_name'] ?? ''),
            'purpose' => trim($_POST['purpose'] ?? ''),
            'appointment_status' => isset($_POST['has_appointment']) ? 'appointment' : 'walk_in',
            'note' => trim($_POST['note'] ?? ''),
        ], (int) Auth::id());

        $queuedCount = $this->queueNotification('entry', $visitId);
        $this->queueMobileNotification('entry', $visitId);
        $reservationless = $this->queueReservationlessReview($visitId);
        $warnings = array_values(array_filter($watchMatches, static fn (array $match): bool => $match['list_type'] === 'warning'));
        $warningText = $warnings ? ' Uyarı listesi eşleşmesi: ' . trim((string) ($warnings[0]['reason'] ?: $warnings[0]['match_value'])) : '';

        if ($warnings) {
            $this->flashSecurityAlert('warning', $fullName, $warnings[0]);
        }

        flash('success', 'Giriş kaydı oluşturuldu.' . $warningText . $this->queueSuffix($queuedCount) . $reservationless);
        redirect('/dashboard');
    }

    public function exit(): never
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/dashboard');
        }

        $visitId = (int) ($_POST['visit_id'] ?? 0);

        if ($visitId <= 0) {
            flash('error', 'Çıkış kaydı için ziyaret seçilmedi.');
            redirect('/dashboard');
        }

        (new Visit())->createExit($visitId, (int) Auth::id(), trim($_POST['exit_note'] ?? '') ?: null);

        $queuedCount = $this->queueNotification('exit', $visitId);
        $this->queueMobileNotification('exit', $visitId);
        flash('success', 'Çıkış kaydı oluşturuldu.' . $this->queueSuffix($queuedCount));
        redirect('/dashboard');
    }

    public function update(): never
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/dashboard');
        }

        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);

        if ($visitId <= 0 || $fullName === '' || $categoryId <= 0) {
            flash('error', 'Güncelleme için ziyaretçi, ad soyad ve kategori zorunludur.');
            redirect('/dashboard');
        }

        $updated = (new Visit())->updateInsideVisit($visitId, [
            'full_name' => $fullName,
            'category_id' => $categoryId,
            'department_id' => (int) ($_POST['department_id'] ?? 0),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'company' => trim((string) ($_POST['company'] ?? '')),
            'vehicle_plate' => $this->formatVehiclePlate(trim((string) ($_POST['vehicle_plate'] ?? ''))),
            'host_name' => trim((string) ($_POST['host_name'] ?? '')),
            'purpose' => trim((string) ($_POST['purpose'] ?? '')),
            'appointment_status' => isset($_POST['has_appointment']) ? 'appointment' : 'walk_in',
            'note' => trim((string) ($_POST['note'] ?? '')),
        ], (int) Auth::id());

        flash($updated ? 'success' : 'error', $updated ? 'Giriş bilgileri güncellendi.' : 'Güncellenecek içeride ziyaretçi bulunamadı.');
        redirect('/dashboard');
    }

    private function queueNotification(string $eventType, int $visitId): ?int
    {
        try {
            return (new NotificationService())->queueForVisitEvent($eventType, $visitId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function queueMobileNotification(string $eventType, int $visitId): void
    {
        try {
            $mobileNotification = new MobileNotification();
            if ($eventType === 'exit') {
                $mobileNotification->queueVisitExit($visitId);
            } else {
                $mobileNotification->queueVisitEntry($visitId);
            }

            (new WebPushService())->sendQueuedForVisit($visitId, $eventType);
        } catch (\Throwable $error) {
            error_log('Mobile notification queue error: ' . $error->getMessage());
        }
    }

    private function queueReservationlessReview(int $visitId): string
    {
        try {
            $result = (new ReservationlessReview())->createForVisitIfNeeded($visitId);
            $message = trim((string) ($result['message'] ?? ''));

            return $message !== '' ? ' ' . $message : '';
        } catch (\Throwable $error) {
            error_log('Reservationless review queue error: ' . $error->getMessage());

            return ' Rezervasyonsuz giriş oda bilgisi akışı kontrol edilmeli.';
        }
    }

    private function queueSuffix(?int $queuedCount): string
    {
        if ($queuedCount === null) {
            return ' Bildirim kuyruğu kontrol edilmeli.';
        }

        if ($queuedCount <= 0) {
            return '';
        }

        return ' Bildirim kuyruğuna ' . $queuedCount . ' kayıt eklendi.';
    }

    private function flashSecurityAlert(string $type, string $fullName, array $match): void
    {
        $reason = trim((string) ($match['reason'] ?? ''));
        $actionNote = trim((string) ($match['action_note'] ?? ''));
        $matchLabel = [
            'name' => 'Ad soyad',
            'phone' => 'Telefon',
            'plate' => 'Plaka',
        ][$match['match_type'] ?? 'name'] ?? 'Eşleşme';
        $matchValue = trim((string) ($match['match_value'] ?? ''));

        if ($type === 'blacklist') {
            flash('security_alert_type', 'blacklist');
            flash('security_alert_title', 'Kara Liste Uyarısı');
            flash('security_alert_message', $fullName . ' için kara liste eşleşmesi bulundu. Giriş kaydı oluşturulmadı.');
        } else {
            flash('security_alert_type', 'warning');
            flash('security_alert_title', 'Uyarı Listesi Eşleşmesi');
            flash('security_alert_message', $fullName . ' için uyarı listesi eşleşmesi bulundu. Giriş kaydı oluşturuldu, lütfen notu okuyun.');
        }

        flash('security_alert_match', $matchLabel . ($matchValue !== '' ? ': ' . $matchValue : ''));
        flash('security_alert_reason', $reason);
        flash('security_alert_action', $actionNote);
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
