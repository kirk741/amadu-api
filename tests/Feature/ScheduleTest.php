<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected $psychologist, $otherPsychologist, $client;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin']);
        $clientRole = Role::create(['name' => 'client']);
        $psychologistRole = Role::create(['name' => 'psychologist']);

        $this->psychologist = User::create(['name' => 'Psy', 'email' => 'a@t.t', 'password' => 'Pass123!', 'role_id' => $psychologistRole->id]);
        $this->otherPsychologist = User::create(['name' => 'otherPsy', 'email' => 'c@t.t', 'password' => 'Pass123!', 'role_id' => $psychologistRole->id]);
        $this->client = User::create(['name' => 'otherPsy', 'email' => 'c1@t.t', 'password' => 'Pass123!', 'role_id' => $clientRole->id]);
    }

    private function createSlot($userId, $isBooked = false, $start = null)
    {
        return Schedule::create([
            'id' => Str::ulid(),
            'user_id' => $userId,
            'start_time' => $start ?? now()->addDay()->toDateTimeString(),
            'end_time' => $start ? Carbon::parse($start)->addHour() : now()->addDay()->addHour(),
            'is_booked' => $isBooked
        ]);
    }

    public function test_guest_can_see_available_slots()
    {
        $this->createSlot($this->psychologist->id, false);
        $this->createSlot($this->psychologist->id, true);

        $response = $this->getJson('/schedules');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.is_booked', false);
    }

    public function test_guest_cannot_manage_schedule()
    {
        $this->postJson('/schedules', ['start_time' => now()->addDay()])
            ->assertStatus(401);

        $this->postJson('/schedules/generate', [])
            ->assertStatus(401);
    }

    public function test_client_cannot_manage_schedule()
    {
        $this->actingAs($this->client);

        $this->postJson('/schedules/generate', [
            'date' => now()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_duration' => 60
        ])->assertStatus(403);
    }

    public function test_psychologist_can_create_single_slot()
    {
        $this->actingAs($this->psychologist);

        $response = $this->postJson('/schedules', [
            'start_time' => '2027-03-08 18:00:00',
            'end_time'   => '2027-03-08 19:00:00'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('schedules', [
            'user_id' => $this->psychologist->id,
            'start_time' => '2027-03-08 18:00:00'
        ]);
    }

    public function test_psychologist_can_generate_slots()
    {
        $this->actingAs($this->psychologist);

        $response = $this->postJson('/schedules/generate', [
            'date' => '2027-03-10',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'slot_duration' => 60
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('count', 3);

        $this->assertDatabaseCount('schedules', 3);
    }

    public function test_generate_skips_existing_slots()
    {
        $this->actingAs($this->psychologist);

        $this->createSlot($this->psychologist->id, false, '2027-03-10 10:00:00');

        $response = $this->postJson('/schedules/generate', [
            'date' => '2027-03-10',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_duration' => 60
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('count', 1);
    }

    public function test_psychologist_cannot_update_others_slot()
    {
        $othersSlot = $this->createSlot($this->otherPsychologist->id, false);

        $this->actingAs($this->psychologist);

        $this->patchJson("/schedules/{$othersSlot->id}", [
            'is_booked' => true
        ])->assertStatus(403);
    }

    public function test_psychologist_cannot_delete_others_slot()
    {
        $othersSlot = $this->createSlot($this->otherPsychologist->id, false);

        $this->actingAs($this->psychologist);

        $this->deleteJson("/schedules/{$othersSlot->id}")
            ->assertStatus(403);
    }

    public function test_psychologist_can_delete_multiple_slots()
    {
        $slot1 = $this->createSlot($this->psychologist->id);
        $slot2 = $this->createSlot($this->psychologist->id);

        $response = $this->actingAs($this->psychologist)
            ->deleteJson('/schedules/bulk', ['ids' => [$slot1->id, $slot2->id]]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('schedules', ['id' => $slot1->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $slot2->id]);
    }
}
