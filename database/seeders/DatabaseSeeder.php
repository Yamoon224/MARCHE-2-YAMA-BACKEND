<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Comptes et données de démonstration : jamais en production (mot de passe connu).
        if (! app()->isProduction()) {
            User::factory()->role(Role::Admin)->create([
                'name' => 'Administrateur',
                'email' => 'admin@marche-yama.test',
                'password' => 'password',
            ]);

            $this->call(CatalogSeeder::class);
            $this->call(DemoSeeder::class);
        }
    }
}
