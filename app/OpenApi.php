<?php

declare(strict_types=1);

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API Marché de Yama',
    description: 'API REST du supermarché en ligne Marché de Yama.',
)]
#[OA\Server(url: '/', description: 'Serveur courant')]
#[OA\SecurityScheme(securityScheme: 'sanctum', type: 'http', scheme: 'bearer', description: 'Jeton Sanctum')]
final class OpenApi {}
