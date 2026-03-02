<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected $admin, $psychologist, $client, $otherPsy;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::create(['name' => 'admin']);
        $psyRole = Role::create(['name' => 'psychologist']);
        $clientRole = Role::create(['name' => 'client']);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.t', 'password' => 'Pass123!', 'role_id' => $adminRole->id]);
        $this->psychologist = User::create(['name' => 'Psy', 'email' => 'p@t.t', 'password' => 'Pass123!', 'role_id' => $psyRole->id]);
        $this->otherPsy = User::create(['name' => 'Other', 'email' => 'o@t.t', 'password' => 'Pass123!', 'role_id' => $psyRole->id]);
        $this->client = User::create(['name' => 'Client', 'email' => 'c@t.t', 'password' => 'Pass123!', 'role_id' => $clientRole->id]);
    }

    public function test_anyone_can_view_events()
    {
        Event::create([
            'user_id' => $this->admin->id,
            'title' => 'Public Event',
            'description' => 'Desc',
            'event_date' => now()->addDays(1),
            'location' => 'Online'
        ]);

        $this->getJson('/events')->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_client_cannot_manage_events()
    {
        $data = [
            'title' => 'Client Event',
            'description' => 'Desc',
            'event_date' => now()->addDays(1),
            'location' => 'Home'
        ];

        $this->actingAs($this->client)->postJson('/events', $data)->assertStatus(403);
    }

    public function test_psychologist_can_manage_only_own_events()
    {
        $myEvent = Event::create([
            'user_id' => $this->psychologist->id,
            'title' => 'My Event',
            'description' => 'Desc',
            'event_date' => now()->addDays(1),
            'location' => 'Office'
        ]);

        $othersEvent = Event::create([
            'user_id' => $this->otherPsy->id,
            'title' => 'Other Event',
            'description' => 'Desc',
            'event_date' => now()->addDays(1),
            'location' => 'Office'
        ]);

        $this->actingAs($this->psychologist)
            ->patchJson("/events/{$myEvent->id}", ['title' => 'New Title'])
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

    public function test_cannot_create_event_in_past()
    {
        $data = [
            'title' => 'Past Event',
            'description' => 'Desc',
            'event_date' => now()->subDays(1),
            'location' => 'Past'
        ];

        $this->actingAs($this->psychologist)
            ->postJson('/events', $data)
            ->assertStatus(422);
    }
}
