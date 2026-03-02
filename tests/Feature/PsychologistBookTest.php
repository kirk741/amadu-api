<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\PsychologistBook;

class PsychologistBookTest extends TestCase
{
    use RefreshDatabase;

    protected $psychologist, $client, $otherPsy;

    protected function setUp(): void
    {
        parent::setUp();
        $psyRole = Role::create(['name' => 'psychologist']);
        $clientRole = Role::create(['name' => 'client']);

        $this->psychologist = User::create(['name' => 'Psy', 'email' => 'p@t.t', 'password' => 'Pass123!', 'role_id' => $psyRole->id]);
        $this->otherPsy = User::create(['name' => 'Other', 'email' => 'o@t.t', 'password' => 'Pass123!', 'role_id' => $psyRole->id]);
        $this->client = User::create(['name' => 'Client', 'email' => 'c@t.t', 'password' => 'Pass123!', 'role_id' => $clientRole->id]);
    }

    public function test_only_psychologist_can_create_book()
    {
        $data = ['title' => 'Psy Book', 'author' => 'Author', 'comment' => 'Nice'];

        $this->actingAs($this->client)->postJson('/books', $data)->assertStatus(403);
        $this->actingAs($this->psychologist)->postJson('/books', $data)->assertStatus(201);
    }

    public function test_psychologist_cannot_update_others_book()
    {
        $book = PsychologistBook::create(['title' => 'Other Title', 'author' => 'A', 'psychologist_id' => $this->otherPsy->id]);

        $this->actingAs($this->psychologist)
            ->patchJson("/books/{$book->id}", ['title' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_anyone_can_view_books()
    {
        PsychologistBook::create(['title' => 'Public', 'author' => 'A', 'psychologist_id' => $this->psychologist->id]);

        $this->getJson('/books')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data'); 
    }
}