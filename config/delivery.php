<?php

return [
    // Délai minimum entre la commande et le début du créneau (préparation + chaîne du froid).
    'min_lead_minutes' => (int) env('DELIVERY_MIN_LEAD_MINUTES', 60),
];
