<?php

return [
    // Délai pour payer avant expiration automatique de la commande et libération du stock.
    'payment_window_minutes' => (int) env('ORDERS_PAYMENT_WINDOW_MINUTES', 15),
];
