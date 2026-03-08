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
    // 1. Доступ и Роли (Security)
    // test_guest_cannot_access_schedule: Гость получает 401 Unauthorized на любые запросы к /schedule.
    // test_client_cannot_access_schedule: Пользователь с ролью client получает 403 Forbidden (проверка твоего мидлвара psychologist).
    // test_psychologist_can_access_schedule: Пользователь с ролью psychologist получает 200/201.
    // 2. Создание и Чтение (CRUD)
    // test_psychologist_can_create_single_slot: Обычный POST / создает запись, и в БД user_id равен ID текущего юзера.
    // test_psychologist_sees_only_relevant_schedules: GET / возвращает список (пагинацию), можно добавить проверку, что психолог видит только свои слоты (если так задумано логикой).
    // test_psychologist_can_view_specific_slot: GET /{id} возвращает данные конкретного слота.
    // 3. Массовая генерация (/generate)
    // test_generate_creates_correct_number_of_slots: Если задать интервал 2 часа и шаг 30 мин, в базе должно появиться ровно 4 новых записи. [1, 2]
    // test_generate_skips_overlapping_slots: Если в интервале уже существует слот (например, на 10:00), метод generate должен пропустить его и не выкинуть ошибку (проверка твоего if (!$exists)).
    // test_generate_fails_with_invalid_times: Проверка валидации: end_time раньше start_time или формат времени не H:i (должно быть 422).
    // test_generate_fails_with_past_date: Нельзя генерировать сетку на вчерашнее число.
    // 4. Права владения (Ownership & Policy)
    // test_psychologist_cannot_update_others_slot: Психолог А пытается сделать PATCH слота Психолога Б. Результат — 403. [3]
    // test_psychologist_cannot_delete_others_slot: Психолог А пытается сделать DELETE слота Психолога Б. Результат — 403.
    // 5. Логика бронирования
    // test_is_booked_field_persists: После создания/генерации is_booked всегда false. При ручном обновлении через PATCH можно ли принудительно поставить true.


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
            'id' => (string) Str::ulid(),
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

        $response = $this->getJson('/schedule');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.is_booked', false);
    }

    public function test_guest_cannot_manage_schedule()
    {
        $this->postJson('/schedule', ['start_time' => now()->addDay()])
            ->assertStatus(401);

        $this->postJson('/schedule/generate', [])
            ->assertStatus(401);
    }

    public function test_client_cannot_manage_schedule()
    {
        $this->actingAs($this->client);

        $this->postJson('/schedule/generate', [
            'date' => now()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_duration' => 60
        ])->assertStatus(403);
    }

    public function test_psychologist_can_create_single_slot()
    {
        $this->actingAs($this->psychologist);

        $response = $this->postJson('/schedule', [
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

        $response = $this->postJson('/schedule/generate', [
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

        $response = $this->postJson('/schedule/generate', [
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

        $this->patchJson("/schedule/{$othersSlot->id}", [
            'is_booked' => true
        ])->assertStatus(403);
    }

    public function test_psychologist_cannot_delete_others_slot()
    {
        $othersSlot = $this->createSlot($this->otherPsychologist->id, false);

        $this->actingAs($this->psychologist);

        $this->deleteJson("/schedule/{$othersSlot->id}")
            ->assertStatus(403);
    }
}
