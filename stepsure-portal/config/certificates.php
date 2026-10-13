<?php

return [

    /*
    | Organisation shown on the verification page and in emails.
    */
    'issuer' => env('CERTIFICATE_ISSUER', 'StepSure Training & Research Institute'),

    'contact_email' => env('CERTIFICATE_CONTACT_EMAIL', 'info@stepsureresearchinstitute.org'),

    'website' => env('CERTIFICATE_WEBSITE', 'https://stepsureresearchinstitute.org'),

    /*
    | Public address printed in every QR code, e.g. https://certificates.stepsureresearchinstitute.org.
    | QR codes are permanent once printed, so this must be the final live address even while the
    | portal runs elsewhere. Leave empty to use the address the portal is currently served from.
    */
    'verify_base_url' => env('CERTIFICATE_VERIFY_URL'),

    /*
    | Certificate artwork: the client's own design with the variable text removed
    | (built by 07-stepsure/tools/build_background.py). Page size matches it: A3 portrait.
    */
    'background' => resource_path('certificate/background.jpg'),

    'paper' => [0, 0, 844.45, 1194.17],

    'default_type' => 'Participation',

];
