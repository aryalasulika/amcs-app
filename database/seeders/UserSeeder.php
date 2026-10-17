<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrator AMCS', 'email' => 'admin@amcs.local', 'role' => 'admin'],
            ['name' => 'Supervisor Operasional', 'email' => 'supervisor@amcs.local', 'role' => 'supervisor'],
            ['name' => 'Petugas AMC', 'email' => 'operator@amcs.local', 'role' => 'amc_operator'],
            ['name' => 'Petugas ATC', 'email' => 'atc@amcs.local', 'role' => 'atc_viewer'],
        ];

        $password = (string) env('AMCS_SEED_PASSWORD', 'Amcs#2026');

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($password),
                    'active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$data['role']]);
        }
    }
}
