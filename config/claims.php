<?php

return [
    // Délai pour réclamer après la livraison (cahier des charges : « délai court »).
    'window_hours' => (int) env('CLAIMS_WINDOW_HOURS', 48),

    'max_photos' => 5,
    'max_photo_kilobytes' => 4096,

    // Disque PRIVÉ : les photos ne sont jamais servies directement, seulement par une route autorisée.
    'disk' => env('CLAIMS_DISK', 'local'),
];
