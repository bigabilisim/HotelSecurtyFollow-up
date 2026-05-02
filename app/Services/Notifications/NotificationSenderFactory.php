<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\Notifications\Senders\MailSender;
use App\Services\Notifications\Senders\NotificationSenderInterface;
use App\Services\Notifications\Senders\TelegramSender;
use App\Services\Notifications\Senders\WhatsAppSender;

final class NotificationSenderFactory
{
    public static function make(string $channelCode): NotificationSenderInterface
    {
        return match ($channelCode) {
            'mail' => new MailSender(),
            'telegram' => new TelegramSender(new SimpleHttpClient()),
            'whatsapp' => new WhatsAppSender(new SimpleHttpClient()),
            default => throw new \InvalidArgumentException('Bilinmeyen bildirim kanalı: ' . $channelCode),
        };
    }
}
