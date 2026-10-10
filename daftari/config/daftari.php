<?php

return [
    // Shown read-only on the admin System settings tab. Bump manually on
    // release — there's no build-time version stamping in this repo.
    'version' => '1.0.0',

    'trial_days' => (int) env('TRIAL_DAYS', 14),
    'default_currency' => env('DEFAULT_CURRENCY', 'SAR'),
    'payment_gateway' => env('PAYMENT_GATEWAY', 'manual'),

    // Daftari's own billing identity, shown on the SaaS subscription
    // receipts issued to companies for their plan payments. VAT/CR number
    // are left null until the operator running this instance sets their
    // real registered numbers — never fabricated, so an unconfigured
    // receipt simply omits those lines rather than showing a fake one.
    'billing' => [
        'company_name' => env('DAFTARI_BILLING_NAME', 'Daftari'),
        'vat_number' => env('DAFTARI_BILLING_VAT_NUMBER'),
        'cr_number' => env('DAFTARI_BILLING_CR_NUMBER'),
        'address' => env('DAFTARI_BILLING_ADDRESS'),
        'email' => env('DAFTARI_BILLING_EMAIL'),
    ],
];
