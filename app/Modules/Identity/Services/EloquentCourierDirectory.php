<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Contracts\CourierDirectory;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

final class EloquentCourierDirectory implements CourierDirectory
{
    public function isCourier(int $userId): bool
    {
        return User::whereKey($userId)->where('role', Role::Courier)->exists();
    }

    public function all(): array
    {
        return User::where('role', Role::Courier)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all();
    }

    public function nameOf(int $userId): ?string
    {
        return User::whereKey($userId)->value('name');
    }
}
