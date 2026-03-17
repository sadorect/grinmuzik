<?php

return [
    'public_disk' => env('MEDIA_PUBLIC_DISK', 'r2_public'),
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'r2_private'),
    'private_url_ttl_minutes' => (int) env('MEDIA_PRIVATE_URL_TTL', 15),
];
