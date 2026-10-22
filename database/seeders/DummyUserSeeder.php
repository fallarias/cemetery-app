<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyUserSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Juan Dela Cruz', 'Maria Santos', 'Jose Reyes', 'Ana Garcia', 'Pedro Ramos',
            'Luz Mendoza', 'Carlos Bautista', 'Rosa Aquino', 'Miguel Torres', 'Elena Flores',
        ];

        foreach ($names as $i => $name) {
            User::forceCreate([
                'name'           => $name,
                'email'          => 'user' . ($i + 1) . '@example.com',
                'password'       => Hash::make('password'),
                'account_type'   => 'User',
                'account_status' => $i % 3 === 0 ? 'inactive' : 'active', // mix of both
            ]);
        }
    }
}
