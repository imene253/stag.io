<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'      => 'Super Admin',
            'email'     => 'admin@univ.dz',
            'password'  => bcrypt('Admin@1234'),
            'role'      => 'admin',
            'is_active' => true,
        ]);
    }
}