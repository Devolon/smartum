<?php

return [
    'jwt_public_key_id' => env('SMARTUM_JWT_PUBLIC_KEY_ID', 'UBJZYFXrOKHq_3VZWIs_XQHDY8ZOS2UrocBvyXm8ejI'),
    'jwt_public_key_path' => storage_path('smartum-public.key'),
    'venue' => env('SMARTUM_VENUE', 'ven_K041DeMhnVKDr3ae'),
    'benefit' => env('SMARTUM_BENEFIT', 'culture'),
    'url' => env('SMARTUM_URL', 'https://api.staging.smartum.fi/checkout'),
];
