<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Usuarios de desarrollo local. No usar en producción.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@colcatrufis.local'], [
            'name' => 'Administrador',
            'password' => 'admin12345',
            'rol' => 'admin',
        ]);

        User::updateOrCreate(['email' => 'demo@colcatrufis.local'], [
            'name' => 'Usuario Demo',
            'password' => 'demo12345',
            'rol' => 'usuario',
        ]);
    }
}
