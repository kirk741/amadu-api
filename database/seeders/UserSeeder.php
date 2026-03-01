<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{

    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@test.test',
            'password' => 'Admin1234!',
            'role_id' => Role::where('name', 'admin')->first()->id,
            'birth_date' => '1990-01-01',
        ]);
    }
}
