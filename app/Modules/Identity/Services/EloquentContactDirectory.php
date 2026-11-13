<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Contracts\ContactDirectory;
use App\Modules\Identity\Models\User;

final class EloquentContactDirectory implements ContactDirectory
{
    public function contactOf(int $userId): ?array
    {
        $user = User::find($userId, ['id', 'name', 'email', 'phone']);

        if ($user === null) {
            return null;
        }

        return ['name' => $user->name, 'email' => $user->email ?: null, 'phone' => $user->phone ?: null];
    }
}
