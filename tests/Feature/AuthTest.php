<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected static $rolesSeeded = false;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'client']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'psychologist']);
        $this->clientRoleId = $role->id;
    }

    public function test_user_can_register()
    {
        $response = $this->postJson('/auth/register', [
            'email' => 'noavatar@example.com',
            'password' => 'StrongPass1!',
            'name' => 'No Avatar User',
            'birth_date' => '2000-01-01',
            'bio' => 'Hello world',
            'role_id' => $this->clientRoleId
        ]);

        $response->assertStatus(201)->assertJsonStructure([
            'success',
            'message',
            'data' => ['token', 'user' => ['id', 'email', 'name', 'birth_date', 'bio']]
        ]);

        $user = User::where('email', 'noavatar@example.com')->first();
        $this->assertNotNull($user);

        $this->assertDatabaseMissing('media', [
            'mediable_type' => User::class,
            'mediable_id' => $user->id
        ]);
    }

    public function test_user_can_register_with_avatar()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/auth/register', [
            'email' => 'avatar@example.com',
            'password' => 'StrongPass1!',
            'name' => 'Avatar User',
            'birth_date' => '2000-01-01',
            'bio' => 'Hello with avatar',
            'avatar' => $file,
            'role_id' => $this->clientRoleId
        ]);

        $response->assertStatus(201);
        $user = User::where('email', 'avatar@example.com')->first();

        $this->assertNotNull($user);

        $this->assertDatabaseHas('media', [
            'mediable_type' => User::class,
            'mediable_id' => $user->id,
            'file_path' => 'avatars/' . $file->hashName(),
        ]);

        Storage::disk('public')->assertExists('avatars/' . $file->hashName());
    }

    public function test_user_can_login_with_correct_credentials()
    {
        $user = User::create([
            'id' => Str::ulid(),
            'email' => 'test@example.com',
            'password' => 'StrongPass1!',
            'name' => 'Test User',
            'role_id' => $this->clientRoleId
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPass1!'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'user' => ['id', 'email', 'name']]
            ]);
    }

    public function test_user_cannot_login_with_wrong_password()
    {
        $user = User::create([
            'id' => Str::ulid(),
            'email' => 'wrong@example.com',
            'password' => 'StrongPass1!',
            'name' => 'Wrong User',
            'role_id' => $this->clientRoleId
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPass1!'
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_logout()
    {
        $user = User::create([
            'id' => Str::ulid(),
            'email' => 'logout@example.com',
            'password' => 'StrongPass1!',
            'name' => 'Logout User',
            'role_id' => $this->clientRoleId
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Выход выполнен',
            ]);
    }
}