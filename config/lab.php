<?php

/*
|--------------------------------------------------------------------------
| AccuCare Pathology Lab settings
|--------------------------------------------------------------------------
|
| Laboratory identity and report-footer details. Everything is read from the
| environment so the values printed on reports are configured by the lab,
| never invented by the application. Leave a value empty to omit it.
|
*/

return [
    'name' => env('LAB_NAME', 'AccuCare Pathology Lab'),
    'tagline' => env('LAB_TAGLINE', 'Accurate diagnostics. Compassionate care.'),
    'address' => env('LAB_ADDRESS', ''),
    'phone' => env('LAB_PHONE', ''),
    'email' => env('LAB_EMAIL', ''),
    'website' => env('LAB_WEBSITE', ''),
    'registration_number' => env('LAB_REGISTRATION_NUMBER', ''),

    // Report number prefix, e.g. ACP-2610-00042.
    'report_prefix' => env('LAB_REPORT_PREFIX', 'ACP'),

    // Bill number prefix, e.g. BILL-2610-00042.
    'bill_prefix' => env('LAB_BILL_PREFIX', 'BILL'),

    // Patient code prefix, e.g. PT-000123.
    'patient_prefix' => env('LAB_PATIENT_PREFIX', 'PT'),

    // Default currency symbol for optional test prices.
    'currency' => env('LAB_CURRENCY', '₹'),

    /*
    | Report signatories. Each entry prints a signature block at the end of a
    | finalized report. Names and titles must be supplied by the laboratory.
    */
    'signatories' => array_values(array_filter([
        env('LAB_SIGNATORY_1_NAME') || env('LAB_SIGNATORY_1_TITLE') ? [
            'name' => env('LAB_SIGNATORY_1_NAME', ''),
            'title' => env('LAB_SIGNATORY_1_TITLE', ''),
        ] : null,
        env('LAB_SIGNATORY_2_NAME') || env('LAB_SIGNATORY_2_TITLE') ? [
            'name' => env('LAB_SIGNATORY_2_NAME', ''),
            'title' => env('LAB_SIGNATORY_2_TITLE', ''),
        ] : null,
    ])),

    // Footer disclaimer printed on every report page.
    'report_footer' => env(
        'LAB_REPORT_FOOTER',
        'Results relate only to the sample received. Please correlate clinically.'
    ),

    // Create synthetic demo patients/reports during `php artisan db:seed`.
    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', false),

    /*
    | Administrator provisioning (used by AdminUserSeeder). The password is
    | never stored in source control; set it in .env for the first seed and
    | remove it afterwards if desired.
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Lab Administrator'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
