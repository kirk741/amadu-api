<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected $admin, $psychologist, $client, $otherPsy;
    protected $adminRole, $psyRole, $clientRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminRole = Role::create(['id' => 1, 'name' => 'admin']);
        $this->psyRole = Role::create(['id' => 3, 'name' => 'psychologist']);
        $this->clientRole = Role::create(['id' => 2, 'name' => 'client']);

        $this->admin = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Admin', 
            'email' => 'a@t.t', 
            'password' => bcrypt('Pass123!'), 
            'role_id' => $this->adminRole->id
        ]);

        $this->psychologist = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Psy', 
            'email' => 'p@t.t', 
            'password' => bcrypt('Pass123!'), 
            'role_id' => $this->psyRole->id
        ]);

        $this->otherPsy = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Other', 
            'email' => 'o@t.t', 
            'password' => bcrypt('Pass123!'), 
            'role_id' => $this->psyRole->id
        ]);

        $this->client = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Client', 
            'email' => 'c@t.t', 
            'password' => bcrypt('Pass123!'), 
            'role_id' => $this->clientRole->id
        ]);
    }

    public function test_anyone_can_view_events_with_pagination()
    {
        Event::create([
            'user_id' => $this->admin->id,
            'title' => 'Public Event',
            'description' => 'Desc',
            'event_date' => now()->addDays(1),
            'location' => 'Online'
        ]);

        $response = $this->getJson('/events');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonStructure([
                'success',
                'data' => ['data', 'current_page', 'total']
            ]);
    }

    public function test_it_can_search_by_title()
    {
        Event::create([
            'user_id' => $this->admin->id,
            'title' => 'Yoga Workshop',
            'description' => 'Relax',
            'event_date' => now()->addDays(1),
            'location' => 'Studio'
        ]);

        Event::create([
            'user_id' => $this->admin->id,
            'title' => 'Coding Bootcamp',
            'description' => 'Laravel',
            'event_date' => now()->addDays(2),
            'location' => 'Office'
        ]);

        $response = $this->getJson('/events?search=Yoga');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Yoga Workshop');
    }

    public function test_it_can_search_by_location()
    {
        Event::create([
            'user_id' => $this->admin->id,
            'title' => 'Conference',
            'description' => 'Tech',
            'event_date' => now()->addDays(1),
            'location' => 'Hall 7'
        ]);

        $response = $this->getJson('/events?search=Hall 7');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.location', 'Hall 7');
    }

    public function test_client_cannot_create_event()
    {
        $data = [
            'title' => 'Hack Event',
            'description' => 'Desc',
            'event_date' => now()->addDays(1),
            'location' => 'Home'
        ];

        $this->actingAs($this->client)
            ->postJson('/events', $data)
            ->assertStatus(403);
    }

    public function test_psychologist_can_manage_only_own_events()
    {
        $myEvent = Event::create([
            'user_id' => $this->psychologist->id,
            'title' => 'My Session',
            'description' => 'Desc',
            'event_date' => now()->addDays(1),
            'location' => 'Office'
        ]);

        $othersEvent = Event::create([
            'user_id' => $this->otherPsy->id,
            'title' => 'Other Session',
            'description' => 'Desc',
            'event_date' => now()->addDays(1),
            'location' => 'Office'
        ]);

        $this->actingAs($this->psychologist)
            ->patchJson("/events/{$myEvent->id}", ['title' => 'Updated Title'])
            ->assertStatus(200);

        $this->actingAs($this->psychologist)
            ->patchJson("/events/{$othersEvent->id}", ['title' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_admin_can_manage_any_event()
    {
        $psyEvent = Event::create([
            'user_id' => $this->psychologist->id,
            'title' => 'Psy Event',
            'description' => 'Desc',
            'event_date' => now()->addDays(1),
            'location' => 'Office'
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/events/{$psyEvent->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('events', ['id' => $psyEvent->id]);
    }

    public function test_cannot_create_event_with_past_date()
    {
        $data = [
            'title' => 'History',
            'description' => 'Desc',
            'event_date' => now()->subDays(1),
            'location' => 'Museum'
        ];

        $this->actingAs($this->psychologist)
            ->postJson('/events', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_date']);
    }
}
