<?php

return [
    'name' => env('APP_NAME', 'Otel Güvenlik Sistemi'),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'Europe/Istanbul'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'session_name' => env('SESSION_NAME', 'hotel_security_session'),
];
