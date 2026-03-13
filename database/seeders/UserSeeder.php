<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder {
    public function run() {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@mail.com',
            'password' => bcrypt('1234'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Customer User',
            'email' => 'customer@mail.com',
            'password' => bcrypt('1234'),
            'email_verified_at' => now(),
            'role' => 'customer',
        ]);
    }
}
