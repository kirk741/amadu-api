<?php

namespace Tests\Feature;

use App\Models\FeelingsDiary;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeelingsDiaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $client, $otherClient;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin']);
        $clientRole = Role::create(['name' => 'client']);

        $this->client = User::create(['name' => 'Client', 'email' => 'c@t.t', 'password' => 'Pass123!', 'role_id' => $clientRole->id])->load('role');
        $this->otherClient = User::create(['name' => 'Other Client', 'email' => 'cl@t.t', 'password' => 'Pass123!', 'role_id' => $clientRole->id])->load('role');
        $this->admin = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('Pass123!'),
            'role_id' => $adminRole->id
        ])->load('role');;
    }

    public function test_client_can_create_diary_with_null_fields()
    {
        $response = $this->actingAs($this->client)->postJson('/feelings-diaries', [
            'situation' => null,
            'thoughts' => 'Только мысли',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.thoughts', 'Только мысли');

        $this->assertDatabaseHas('feelings_diaries', ['user_id' => $this->client->id]);
    }

    public function test_client_can_only_see_their_own_diaries()
    {
        $this->client->feelingsDiaries()->create(['thoughts' => 'Моя запись']);
        $this->otherClient->feelingsDiaries()->create(['thoughts' => 'Чужая запись']);

        $response = $this->actingAs($this->client)->getJson('/feelings-diaries');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.thoughts', 'Моя запись');
    }

    public function test_client_cant_view_someone_elses_diary()
    {
        $othersDiary = $this->otherClient->feelingsDiaries()->create(['thoughts' => 'Secret']);

        $response = $this->actingAs($this->client)
            ->getJson("/feelings-diaries/{$othersDiary->id}");

        $response->assertStatus(403);
    }

    public function test_client_can_update_their_own_diary()
    {
        $diary = $this->client->feelingsDiaries()->create(['thoughts' => 'Old']);

        $response = $this->actingAs($this->client)
            ->patchJson("/feelings-diaries/{$diary->id}", [
                'situation' => 'Обновлено',
                'feelings' => null
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('feelings_diaries', [
            'id' => $diary->id,
            'situation' => 'Обновлено'
        ]);
    }

    public function test_client_can_delete_their_own_diary()
    {
        $diary = $this->client->feelingsDiaries()->create(['thoughts' => 'Delete me']);

        $response = $this->actingAs($this->client)
            ->deleteJson("/feelings-diaries/{$diary->id}/force");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('feelings_diaries', ['id' => $diary->id]);
    }

    public function test_non_client_cant_create_diary()
    {

        $response = $this->actingAs($this->admin)
            ->postJson('/feelings-diaries', ['situation' => 'Test']);

        $response->assertStatus(403);
    }

    public function test_client_can_soft_delete_own_diary()
    {
        $diary = $this->client->feelingsDiaries()->create(['thoughts' => 'В корзину']);

        $response = $this->actingAs($this->client)
            ->deleteJson("/feelings-diaries/{$diary->id}/soft");

        $response->assertStatus(200);
        $this->assertSoftDeleted('feelings_diaries', ['id' => $diary->id]);
    }


    public function test_client_can_see_only_deleted_diaries_in_trash()
    {
        $this->client->feelingsDiaries()->create(['thoughts' => 'Живой']);
        $deleted = $this->client->feelingsDiaries()->create(['thoughts' => 'Удаленный']);
        $deleted->delete();

        $response = $this->actingAs($this->client)->getJson('/feelings-diaries/trash');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.thoughts', 'Удаленный');
    }

    public function test_client_can_restore_own_diary()
    {
        $diary = $this->client->feelingsDiaries()->create(['thoughts' => 'Восстановить']);
        $diary->delete();

        $response = $this->actingAs($this->client)
            ->postJson("/feelings-diaries/{$diary->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted('feelings_diaries', ['id' => $diary->id]);
    }

    public function test_client_can_force_delete_own_diary()
    {
        $diary = $this->client->feelingsDiaries()->create(['thoughts' => 'Навсегда']);

        $response = $this->actingAs($this->client)
            ->deleteJson("/feelings-diaries/{$diary->id}/force");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('feelings_diaries', ['id' => $diary->id]);
    }

    public function test_client_cannot_restore_others_diary()
    {
        $othersDiary = $this->otherClient->feelingsDiaries()->create(['thoughts' => 'Чужое']);
        $othersDiary->delete();

        $response = $this->actingAs($this->client)
            ->postJson("/feelings-diaries/{$othersDiary->id}/restore");

        $response->assertStatus(403);
    }
}
