<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * Premier compte administrateur. En production, il n'est créé que si ADMIN_EMAIL et ADMIN_PASSWORD sont définis
 * dans l'environnement (aucun mot de passe connu n'est jamais embarqué dans le code) et il n'écrase jamais un compte existant.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isProduction()) {
            User::firstOrCreate(['email' => 'admin@marche-yama.test'], [
                'name' => 'Administrateur', 'password' => 'password', 'role' => Role::Admin,
            ]);

            return;
        }

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD absents : aucun administrateur créé.');

            return;
        }

        if (strlen($password) < 12) {
            $this->command?->error('ADMIN_PASSWORD doit faire au moins 12 caractères : administrateur non créé.');

            return;
        }

        User::firstOrCreate(['email' => $email], [
            'name' => env('ADMIN_NAME', 'Administrateur'), 'password' => $password, 'role' => Role::Admin,
        ]);
    }
}
