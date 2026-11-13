<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginAction
{
    /** @return array{user: User, token: string} */
    public function execute(string $login, string $password): array
    {
        $user = User::where('email', $login)->orWhere('phone', $login)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['login' => ['Identifiants incorrects.']]);
        }

        return ['user' => $user, 'token' => $user->createToken('api')->plainTextToken];
    }
}
