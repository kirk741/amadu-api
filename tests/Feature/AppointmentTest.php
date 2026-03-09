<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $psychologist, $client;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::create(['id' => 1, 'name' => 'admin']);
        $clientRole = Role::create(['id' => 2, 'name' => 'client']);
        $psychologistRole = Role::create(['id' => 3, 'name' => 'psychologist']);

        $this->psychologist = User::create([
            'id' => Str::ulid(),
            'name' => 'Dr. House',
            'email' => 'h@t.t',
            'password' => bcrypt('Pass123!'),
            'role_id' => $psychologistRole->id
        ]);

        $this->client = User::create([
            'id' => Str::ulid(),
            'name' => 'Patient',
            'email' => 'p@t.t',
            'password' => bcrypt('Pass123!'),
            'role_id' => $clientRole->id
        ]);
    }

    public function test_client_can_book_available_slot()
    {
        $slot = Schedule::create([
            'user_id' => $this->psychologist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'is_booked' => false
        ]);

        $response = $this->actingAs($this->client)->postJson('/appointments', ['schedule_id' => $slot->id]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('appointments', ['client_id' => $this->client->id]);
        $this->assertDatabaseHas('schedules', ['id' => $slot->id, 'is_booked' => true]);
    }

    public function test_cannot_book_already_booked_slot()
    {
        $slot = Schedule::create([
            'user_id' => $this->psychologist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'is_booked' => true
        ]);

        $response = $this->actingAs($this->client)->postJson('/appointments', [
            'schedule_id' => $slot->id
        ])->assertStatus(422);
    }

    public function test_client_cannot_set_completed_status()
    {
        $slot = Schedule::create([
            'user_id' => $this->psychologist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'is_booked' => true
        ]);

        $appointment = Appointment::create([
            'schedule_id' => $slot->id,
            'psychologist_id' => $this->psychologist->id,
            'client_id' => $this->client->id,
            'status' => 'scheduled'
        ]);

        $this->actingAs($this->client)->patchJson("/appointments/{$appointment->id}", [
            'status' => 'completed'
        ])->assertStatus(403);
    }

    public function test_cancellation_frees_the_slot()
    {
        $slot = Schedule::create([
            'user_id' => $this->psychologist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'is_booked' => true
        ]);

        $appointment = Appointment::create([
            'schedule_id' => $slot->id,
            'psychologist_id' => $this->psychologist->id,
            'client_id' => $this->client->id,
            'status' => 'scheduled'
        ]);

        $this->actingAs($this->client)->deleteJson("/appointments/{$appointment->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('schedules', ['id' => $slot->id, 'is_booked' => false]);
    }
}
