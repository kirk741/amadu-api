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

    public function test_psychologist_can_generate_slots_for_multiple_dates()
    {
        $this->actingAs($this->psychologist);

        $response = $this->postJson('/schedules/generate', [
            'dates' => ['2027-05-10', '2027-05-11'], // Массив дат
            'start_time' => '09:00',
            'end_time' => '11:00',
            'slot_duration' => 60
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('count', 4);

        $this->assertDatabaseCount('schedules', 4);
        $this->assertDatabaseHas('schedules', ['start_time' => '2027-05-10 09:00:00']);
        $this->assertDatabaseHas('schedules', ['start_time' => '2027-05-11 10:00:00']);
    }

    public function test_generate_returns_error_if_all_slots_already_exist()
    {
        $this->actingAs($this->psychologist);

        $this->createSlot($this->psychologist->id, false, '2027-06-01 10:00:00');

        $response = $this->postJson('/schedules/generate', [
            'dates' => ['2027-06-01'],
            'start_time' => '10:00',
            'end_time' => '11:00',
            'slot_duration' => 60
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Слоты уже существуют или интервал слишком мал']);
    }

    public function test_generate_validation_fails_for_invalid_times()
    {
        $this->actingAs($this->psychologist);

        $response = $this->postJson('/schedules/generate', [
            'dates' => ['2027-03-10'],
            'start_time' => '15:00',
            'end_time' => '10:00', // Конец раньше начала
            'slot_duration' => 60
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_psychologist_cannot_create_overlapping_slots()
    {
        $this->actingAs($this->psychologist);
        $this->createSlot($this->psychologist->id, false, '2027-10-10 10:00:00');

        $response = $this->postJson('/schedules', [
            'start_time' => '2027-10-10 10:00:00',
            'end_time'   => '2027-10-10 11:00:00'
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Это время уже занято другим слотом в вашем расписании']);

        $responseOverlap = $this->postJson('/schedules', [
            'start_time' => '2027-10-10 10:30:00',
            'end_time'   => '2027-10-10 11:30:00'
        ]);

        $responseOverlap->assertStatus(422);
        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_psychologist_cannot_update_slot_to_overlapping_time()
    {
        $this->actingAs($this->psychologist);

        $slot1 = $this->createSlot($this->psychologist->id, false, '2027-12-12 10:00:00');
        $slot2 = $this->createSlot($this->psychologist->id, false, '2027-12-12 12:00:00');

        $response = $this->patchJson("/schedules/{$slot2->id}", [
            'start_time' => '2027-12-12 10:30:00',
            'end_time'   => '2027-12-12 11:30:00'
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Это время уже занято другим слотом в вашем расписании']);

        $this->patchJson("/schedules/{$slot1->id}", [
            'is_booked' => true
        ])->assertStatus(200);
    }
}
