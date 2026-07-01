<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@test.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin1234!'), 
                'role_id' => Role::where('name', 'admin')->first()->id,
                'birth_date' => '1990-01-01',
            ]
        );

        $psyRoleId = Role::where('name', 'psychologist')->first()?->id;

        User::updateOrCreate(
            ['email' => 'bot@ai.assistant'],
            [
                'id' => '01JB0T0AI0ASS1STANT0000000', 
                'name' => 'ИИ-Ассистент',
                'password' => Hash::make('BotAISecretPass123!'),
                'role_id' => $psyRoleId,
                'birth_date' => '2026-07-01',
                'bio' => 'Бережный искусственный интеллект. Помогу справиться со стрессом и тревожностью в любое время.',
            ]
        );
    }
}
