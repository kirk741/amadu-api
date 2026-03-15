<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $clientRole = Role::create(['id' => 2, 'name' => 'client']);

        $this->user = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('Pass123!'),
            'role_id' => $clientRole->id,
            'settings' => ['theme' => 'moon-theme', 'notifications' => true]
        ]);
    }

    public function test_user_can_update_theme_setting()
    {
        $response = $this->actingAs($this->user)
            ->patchJson('/user/settings', [
                'theme' => 'dark-theme'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.theme', 'dark-theme')
            ->assertJsonPath('data.notifications', true);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'settings->theme' => 'dark-theme'
        ]);
    }

    public function test_user_can_update_notification_setting()
    {
        $response = $this->actingAs($this->user)
            ->patchJson('/user/settings', [
                'notifications' => false
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.theme', 'moon-theme')
            ->assertJsonPath('data.notifications', false);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'settings->notifications' => 'false'
        ]);
    }
}
