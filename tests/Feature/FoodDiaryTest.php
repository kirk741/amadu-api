<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoodDiaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $client, $otherClient;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $adminRole = Role::create(['name' => 'admin']);
        $clientRole = Role::create(['name' => 'client']);

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
    }


    public function test_client_can_create_diary_with_content_only()
    {
        $response = $this->actingAs($this->client)->postJson('/food-diaries', [
            'content' => 'Сегодня ел яблоки',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('food_diaries', ['content' => 'Сегодня ел яблоки']);
    }

    public function test_client_can_create_diary_with_cover_only()
    {
        $file = UploadedFile::fake()->image('pizza.jpg');

        $response = $this->actingAs($this->client)->postJson('/food-diaries', [
            'cover' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.media'));
    }

    public function test_it_fails_if_both_content_and_cover_are_missing()
    {
        $response = $this->actingAs($this->client)->postJson('/food-diaries', [
            'title' => 'Пустой дневник',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['content', 'cover']);
    }

    public function test_update_replaces_old_file_physically()
    {
        $diary = $this->client->foodDiaries()->create([
            'title' => now()->format('Y-m-d H:i:s'),
            'content' => 'Test'
        ]);

        $oldPath = 'foodDiaryCovers/old.jpg';
        Storage::disk('local')->put($oldPath, 'content');

        $diary->media()->create([
            'file_path' => $oldPath,
            'collection' => 'foodDiaryCovers',
            'mime_type' => 'image/jpeg',
            'size' => 1024
        ]);

        $newFile = UploadedFile::fake()->image('new.png');
        $response = $this->actingAs($this->client)->patchJson("/food-diaries/{$diary->id}", [
            'cover' => $newFile,
            'content' => 'Updated content'
        ]);

        $response->assertStatus(200);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($response->json('data.media.file_path'));
    }

    public function test_client_can_soft_delete_own_diary()
    {
        $diary = $this->client->foodDiaries()->create([
            'title' => now()->format('Y-m-d H:i:s'),
            'content' => 'В корзину'
        ]);

        $response = $this->actingAs($this->client)->deleteJson("/food-diaries/{$diary->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('food_diaries', ['id' => $diary->id]);
    }

    public function test_client_can_see_only_deleted_diaries_in_trash()
    {
        $this->client->foodDiaries()->create(['title' => 'T1', 'content' => 'Живой']);
        $deleted = $this->client->foodDiaries()->create(['title' => 'T2', 'content' => 'Мертвый']);
        $deleted->delete();

        $response = $this->actingAs($this->client)->getJson('/food-diaries/trash');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.content', 'Мертвый');
    }

    public function test_client_can_restore_diary()
    {
        $diary = $this->client->foodDiaries()->create(['title' => 'T1', 'content' => 'Back to life']);
        $diary->delete();

        $response = $this->actingAs($this->client)->postJson("/food-diaries/{$diary->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted('food_diaries', ['id' => $diary->id]);
    }

    public function test_force_delete_removes_record_and_file()
    {
        $path = 'foodDiaryCovers/killme.jpg';
        $diary = $this->client->foodDiaries()->create([
            'title' => 'Kill me',
            'content' => 'Goodbye'
        ]);

        Storage::disk('local')->put($path, 'content');
        $diary->media()->create([
            'file_path' => $path,
            'collection' => 'foodDiaryCovers',
            'mime_type' => 'image/jpeg',
            'size' => 1024
        ]);

        $response = $this->actingAs($this->client)->deleteJson("/food-diaries/{$diary->id}/force");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('food_diaries', ['id' => $diary->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_client_cannot_view_other_clients_diary()
    {
        $othersDiary = $this->otherClient->foodDiaries()->create([
            'title' => 'Secret',
            'content' => 'Чужой секрет'
        ]);

        $response = $this->actingAs($this->client)->getJson("/food-diaries/{$othersDiary->id}");

        $response->assertStatus(403);
    }

    public function test_client_can_search_food_diary_by_title_or_content()
    {
        $this->client->foodDiaries()->create([
            'title' => 'Завтрак чемпиона',
            'content' => 'Овсянка и кофе'
        ]);

        $this->client->foodDiaries()->create([
            'title' => 'Обед',
            'content' => 'Курица с рисом'
        ]);

        $response = $this->actingAs($this->client)
            ->getJson('/food-diaries?search=Завтрак');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.content', 'Овсянка и кофе');

        $response = $this->actingAs($this->client)
            ->getJson('/food-diaries?search=курица');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Обед');
    }

    public function test_food_diary_search_is_scoped_to_user()
    {
        $this->otherClient->foodDiaries()->create([
            'title' => 'Пицца',
            'content' => 'Много сыра'
        ]);

        $response = $this->actingAs($this->client)
            ->getJson('/food-diaries?search=Пицца');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.data');
    }

    public function test_food_diary_index_returns_all_paginated_records_without_search()
    {
        $this->client->foodDiaries()->create(['title' => 'T1', 'content' => 'C1']);
        $this->client->foodDiaries()->create(['title' => 'T2', 'content' => 'C2']);

        $response = $this->actingAs($this->client)->getJson('/food-diaries');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    }
}
