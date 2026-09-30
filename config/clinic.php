<?php

return [
    'name' => env('CLINIC_NAME', 'Clínica Sorriso e Vida'),
    'address' => env('CLINIC_ADDRESS', ''), 'phone' => env('CLINIC_PHONE', ''),
    'booking' => ['minimum_notice_hours' => (int) env('BOOKING_MINIMUM_NOTICE_HOURS', 2), 'max_days_ahead' => (int) env('BOOKING_MAX_DAYS_AHEAD', 90)],
    'session_timeout_minutes' => (int) env('WHATSAPP_SESSION_TIMEOUT_MINUTES', 30),
];
