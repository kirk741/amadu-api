<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersonalDiaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user, $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['id' => 1, 'name' => 'admin']);
        $clientRole = Role::create(['id' => 2, 'name' => 'client']);

        $this->user = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Client',
            'email' => 'c@t.t',
            'password' => bcrypt('Pass123!'),
            'role_id' => $clientRole->id
        ])->load('role');

        $this->otherUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Other',
            'email' => 'o@t.t',
            'password' => bcrypt('Pass123!'),
            'role_id' => $clientRole->id
        ])->load('role');
    }

    public function test_user_can_create_diary_entry()
    {
        $response = $this->actingAs($this->user)->postJson('/personal-diaries', [
            'title' => 'Мой день',
            'content' => 'Сегодня был отличный день.'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('personal_diaries', ['title' => 'Мой день']);
    }

    public function test_user_can_see_only_own_diaries()
    {
        $this->user->personalDiaries()->create(['title' => 'Мой', 'content' => '...']);
        $this->otherUser->personalDiaries()->create(['title' => 'Чужой', 'content' => '...']);

        $response = $this->actingAs($this->user)->getJson('/personal-diaries');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Мой');
    }

    public function test_user_can_update_own_entry()
    {
        $diary = $this->user->personalDiaries()->create(['title' => 'Старый', 'content' => '...']);

        $response = $this->actingAs($this->user)->patchJson("/personal-diaries/{$diary->id}", [
            'title' => 'Новый заголовок'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('personal_diaries', ['id' => $diary->id, 'title' => 'Новый заголовок']);
    }

    public function test_user_can_soft_delete_entry()
    {
        $diary = $this->user->personalDiaries()->create(['title' => 'В корзину', 'content' => '...']);

        $this->actingAs($this->user)->deleteJson("/personal-diaries/{$diary->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('personal_diaries', ['id' => $diary->id]);
    }

    public function test_user_can_restore_entry()
    {
        $diary = $this->user->personalDiaries()->create(['title' => 'Удален', 'content' => '...']);
        $diary->delete();

        $this->actingAs($this->user)->postJson("/personal-diaries/{$diary->id}/restore")
            ->assertStatus(200);

        $this->assertDatabaseHas('personal_diaries', ['id' => $diary->id, 'deleted_at' => null]);
    }

    public function test_user_cannot_access_others_diary()
    {
        $othersDiary = $this->otherUser->personalDiaries()->create(['title' => 'Чужой', 'content' => '...']);

        $this->actingAs($this->user)->getJson("/personal-diaries/{$othersDiary->id}")
            ->assertStatus(403);
    }
}
