<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $psychologist;
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin']);
        $psyRole = Role::create(['name' => 'psychologist']);
        $clientRole = Role::create(['name' => 'client']);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@t.t',
            'password' => 'Pass1234!',
            'role_id' => $adminRole->id
        ]);
        $this->psychologist = User::create([
            'name' => 'Psy',
            'email' => 'psy@t.t',
            'password' => 'Pass1234!',
            'role_id' => $psyRole->id
        ]);
        $this->client = User::create([
            'name' => 'Client',
            'email' => 'client@t.t',
            'password' => 'Pass1234!',
            'role_id' => $clientRole->id
        ]);
    }

    public function test_user_can_see_his_own_profile()
    {
        $response = $this->actingAs($this->client)
            ->getJson('user/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $this->client->email);
    }

    public function test_user_can_update_his_profile_with_avatar()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($this->client)
            ->patchJson('user/me', [
                'name' => 'New Name',
                'avatar' => $file
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $this->client->id, 'name' => 'New Name']);
        $this->assertDatabaseHas('media', ['mediable_id' => $this->client->id]);
    }

    public function test_client_cant_search_clients()
    {
        $response = $this->actingAs($this->client)
            ->getJson('/user?search=Client');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.data');
    }

    public function test_psychologist_can_search_only_clients()
    {
        $response = $this->actingAs($this->psychologist)
            ->getJson('/user?search=Client');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Client');

        $response = $this->actingAs($this->psychologist)
            ->getJson('/user?search=Admin');

        $response->assertJsonCount(0, 'data.data');
    }

    public function test_not_admin_cant_block_user()
    {
        $response = $this->actingAs($this->client)
            ->postJson("/user/{$this->psychologist->id}/block");

        $response->assertStatus(403);
        $this->assertFalse($this->client->fresh()->is_blocked);
    }

    public function test_not_admin_cant_set_roles()
    {
        $response = $this->actingAs($this->client)
            ->patchJson("/user/{$this->psychologist->id}/set-role", [
                'role_id' => 1
            ]);

        $response->assertStatus(403);
    }

    public function test_blocked_user_cannot_access_api()
    {
        $this->client->update(['is_blocked' => true]);

        $response = $this->actingAs($this->client)
            ->getJson('user/me');

        $response->assertStatus(403);
    }
}
