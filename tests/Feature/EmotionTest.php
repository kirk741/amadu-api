<?php

namespace Tests\Feature;

use App\Models\Emotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmotionTest extends TestCase
{
    use RefreshDatabase;

    protected $admin, $client;

    public function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin']);
        $clientRole = Role::create(['name' => 'client']);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.t', 'password' => 'Pass123!', 'role_id' => $adminRole->id]);
        $this->client = User::create(['name' => 'Client', 'email' => 'c@t.t', 'password' => 'Pass123!', 'role_id' => $clientRole->id]);
    }

    public function test_anyone_can_watch_emotions()
    {
        Emotion::create([
            'name' => 'радость'
        ]);

        $this->getJson('/emotions')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_client_cant_manage_emotions()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('icon.svg');
        $emotion = Emotion::create([
            'name' => 'радость'
        ]);

        $this->actingAs($this->client)->postJson("/emotions", ['name' => 'гнев', 'icon' => $file])
            ->assertStatus(403);

        $this->actingAs($this->client)->deleteJson("/emotions/$emotion->id")
            ->assertStatus(403);

        $this->actingAs($this->client)->patchJson("/emotions/$emotion->id", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_admin_can_manage_emotions()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('icon.svg', 100, 'image/svg+xml');
        $emotion = Emotion::create(['name' => 'радость']);


        $response = $this->actingAs($this->admin)->postJson("/emotions", ['name' => 'гнев', 'icon' => $file])
            ->assertStatus(201);

        $path = $response->json('data.media.0.file_path');
        $id = $response->json('data.id');
        $this->actingAs($this->admin)->deleteJson("/emotions/$id")
            ->assertStatus(200);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('emotions', ['id' => $id]);

        $this->actingAs($this->admin)->patchJson("/emotions/$emotion->id", ['name' => 'Hacked'])
            ->assertStatus(200);
    }
}
