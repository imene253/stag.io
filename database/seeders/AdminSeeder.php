<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@univ.dz'],
            [
                'name'      => 'Super Admin',
                'password'  => bcrypt('Admin@1234'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );
    }
}