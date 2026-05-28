<?php

return [
    'code_length' => 6,
    'code_lifetime_minutes' => (int) env('TWO_FACTOR_CODE_LIFETIME', 10),
    'max_attempts' => 5,
    'recovery_code_count' => 8,
    'trusted_device_days' => (int) env('TWO_FACTOR_TRUSTED_DEVICE_DAYS', 30),
    'resend_cooldown_seconds' => 60,
    'trusted_device_cookie' => 'rice_2fa_trust',
    'pending_session_lifetime_minutes' => 15,
];
