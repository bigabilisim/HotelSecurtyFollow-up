<?php

declare(strict_types=1);

namespace App\Services\Notifications\Senders;

interface NotificationSenderInterface
{
    public function send(array $notification, array $config): array;
}
