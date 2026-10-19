<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données initiales uniquement. Les données de démonstration (fictives) sont dans
     * DemoSeeder, à lancer explicitement : php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            SpecialiteSeeder::class,
            AdministrateurSeeder::class,
        ]);
    }
}
