<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Str;

final class CreateGuestCustomerAction
{
    /** @param array{name: string, email: string, phone: string} $data */
    public function execute(array $data): User
    {
        // Mot de passe aléatoire jamais communiqué : un invité ne peut pas se reconnecter, seul son jeton de session le représente.
        return User::create([
            'name' => $data['name'],
            'role' => Role::Customer,
            'password' => Str::random(64),
            'is_guest' => true,
            'contact_email' => $data['email'],
            'contact_phone' => preg_replace('/[\s.-]/', '', $data['phone']),
        ]);
    }
}
