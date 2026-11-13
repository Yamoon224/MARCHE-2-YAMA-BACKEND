<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+22507'.fake()->unique()->numerify('########'),
            'password' => 'password',
            'role' => Role::Customer,
            'locale' => 'fr',
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(['role' => $role]);
    }
}
