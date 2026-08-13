<?php

return [
    'trial_days' => (int) env('TRIAL_DAYS', 14),
    'default_currency' => env('DEFAULT_CURRENCY', 'SAR'),
    'payment_gateway' => env('PAYMENT_GATEWAY', 'manual'),
];
