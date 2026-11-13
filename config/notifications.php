<?php

return [
    // « log » : fournisseur de test (écrit dans les journaux, n'envoie rien). Refusé en production tant qu'aucun
    // vrai fournisseur SMS / e-mail n'est branché : la notification dans l'application reste, l'envoi est marqué en échec.
    'driver' => env('NOTIFICATIONS_DRIVER', 'log'),

    // Envois SMS / e-mail tentés pour chaque notification (en plus du fil dans l'application).
    'media' => ['sms', 'email'],

    // Un envoi en échec est repris par `notifications:retry-failed` jusqu'à ce nombre de tentatives.
    'max_attempts' => 5,

    // Simulateur d'incident fournisseur (tests et recette uniquement).
    'fail_sends' => (bool) env('NOTIFICATIONS_FAIL_SENDS', false),
];
