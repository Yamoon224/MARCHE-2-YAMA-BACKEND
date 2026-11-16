<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Données préliminaires, rejouables sans risque et valables en production.
        $this->call([AdminSeeder::class, ReferenceSeeder::class, CatalogSeeder::class]);

        // Comptes de test et stock fictif : jamais en production (mots de passe connus).
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
