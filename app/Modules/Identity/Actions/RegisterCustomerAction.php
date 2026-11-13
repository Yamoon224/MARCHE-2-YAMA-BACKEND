<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

final class RegisterCustomerAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): User
    {
        return User::create([...$data, 'role' => Role::Customer]);
    }
}
