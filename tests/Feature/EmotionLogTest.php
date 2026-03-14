<?php

namespace Tests\Feature;

use App\Models\Emotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmotionLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $client, $otherClient;
    protected Emotion $emotion;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['id' => 1, 'name' => 'admin']);
        $clientRole = Role::create(['id' => 2, 'name' => 'client']);

        $this->client = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Client',
            'email' => 'c@t.t',
            'password' => bcrypt('Pass123!'),
            'role_id' => $clientRole->id
        ])->load('role');

        $this->otherClient = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Other',
            'email' => 'o@t.t',
            'password' => bcrypt('Pass123!'),
            'role_id' => $clientRole->id
        ])->load('role');

        $this->emotion = Emotion::create(['name' => 'Радость']);
    }

    public function test_client_can_log_emotion()
    {
        $response = $this->actingAs($this->client)->postJson('/emotion-logs', [
            'emotion_id' => $this->emotion->id,
            'created_at' => now()
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('emotion_logs', [
            'user_id' => $this->client->id,
            'emotion_id' => $this->emotion->id
        ]);
    }

    public function test_it_fails_with_invalid_emotion_id()
    {
        $response = $this->actingAs($this->client)->postJson('/emotion-logs', [
            'emotion_id' => 'non-existent-id'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['emotion_id']);
    }

    public function test_client_sees_only_own_logs_in_index()
    {
        $this->client->emotionLogs()->create(['emotion_id' => $this->emotion->id]);
        $this->otherClient->emotionLogs()->create(['emotion_id' => $this->emotion->id]);

        $response = $this->actingAs($this->client)->getJson('/emotion-logs');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_client_can_manage_own_log()
    {
        $log = $this->client->emotionLogs()->create(['emotion_id' => $this->emotion->id]);
        $newEmotion = Emotion::create(['name' => 'Грусть']);

        $this->actingAs($this->client)->getJson("/emotion-logs/{$log->id}")
            ->assertStatus(200);

        $this->actingAs($this->client)->patchJson("/emotion-logs/{$log->id}", [
            'emotion_id' => $newEmotion->id
        ])->assertStatus(200);

        $this->actingAs($this->client)->deleteJson("/emotion-logs/{$log->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('emotion_logs', ['id' => $log->id]);
    }

    public function test_client_cannot_access_others_log()
    {
        $othersLog = $this->otherClient->emotionLogs()->create(['emotion_id' => $this->emotion->id]);

        $this->actingAs($this->client)->getJson("/emotion-logs/{$othersLog->id}")
            ->assertStatus(403);

        $this->actingAs($this->client)->patchJson("/emotion-logs/{$othersLog->id}", [
            'emotion_id' => $this->emotion->id
        ])->assertStatus(403);

        $this->actingAs($this->client)->deleteJson("/emotion-logs/{$othersLog->id}")
            ->assertStatus(403);
    }
   public function test_it_can_search_by_emotion_name()
    {
        $happy = Emotion::create(['name' => 'Happy']);
        $sad = Emotion::create(['name' => 'Sad']);

        $this->client->emotionLogs()->create(['emotion_id' => $happy->id]);
        $this->client->emotionLogs()->create(['emotion_id' => $sad->id]);

        $response = $this->actingAs($this->client)->getJson('/emotion-logs?search=Happy');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.emotion.name', 'Happy');
    }

    public function test_it_can_search_by_date()
    {
        $this->client->emotionLogs()->create([
            'emotion_id' => $this->emotion->id,
            'created_at' => '2025-10-10 10:00:00'
        ]);
        $this->client->emotionLogs()->create([
            'emotion_id' => $this->emotion->id,
            'created_at' => '2026-01-01 10:00:00'
        ]);

        $response = $this->actingAs($this->client)->getJson('/emotion-logs?search=2025');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');

        $this->assertStringContainsString('2025-10-10', $response->getContent());
    }

    public function test_it_does_not_show_other_users_logs_even_if_they_match_search()
    {
        $matchingEmotion = Emotion::create(['name' => 'MatchMe']);

        $this->otherClient->emotionLogs()->create(['emotion_id' => $matchingEmotion->id]);

        $response = $this->actingAs($this->client)->getJson('/emotion-logs?search=MatchMe');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.data');
    }
}
