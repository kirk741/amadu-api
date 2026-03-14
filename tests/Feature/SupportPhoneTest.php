<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SupportPhone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SupportPhoneTest extends TestCase
{
    use RefreshDatabase;

    protected $admin, $client;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::create(['name' => 'admin']);
        $clientRole = Role::create(['name' => 'client']);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.t', 'password' => 'Pass123!', 'role_id' => $adminRole->id]);
        $this->client = User::create(['name' => 'Client', 'email' => 'c@t.t', 'password' => 'Pass123!', 'role_id' => $clientRole->id]);
    }

    public function test_only_admin_can_manage_phones()
    {
        $data = ['phone' => '8800', 'title' => 'Help', 'description' => 'Desc'];

        $this->actingAs($this->client)->postJson('/support-phones', $data)->assertStatus(403);
        $this->actingAs($this->admin)->postJson('/support-phones', $data)->assertStatus(201);
    }

    public function test_guest_can_view_phones()
    {
        SupportPhone::create(['phone' => '111', 'title' => 'T', 'description' => 'D']);

        $this->getJson('/support-phones')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

     public function test_it_can_search_phones_by_number_title_or_description()
    {
        SupportPhone::create([
            'phone' => '112',
            'title' => 'Emergency',
            'description' => 'Rescue service'
        ]);

        SupportPhone::create([
            'phone' => '103',
            'title' => 'Ambulance',
            'description' => 'Medical help'
        ]);

        $this->getJson('/support-phones?search=112')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.phone', '112');

        $this->getJson('/support-phones?search=Ambulance')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Ambulance');

        $this->getJson('/support-phones?search=Rescue')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.description', 'Rescue service');
    }

    public function test_search_returns_empty_data_if_no_matches_found()
    {
        SupportPhone::create(['phone' => '123', 'title' => 'Test', 'description' => 'Desc']);

        $response = $this->getJson('/support-phones?search=NonExistentWord');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.data');
    }

    public function test_search_returns_all_phones_if_search_parameter_is_empty()
    {
        SupportPhone::create(['phone' => '111', 'title' => 'T1', 'description' => 'D1']);
        SupportPhone::create(['phone' => '222', 'title' => 'T2', 'description' => 'D2']);

        $response = $this->getJson('/support-phones?search=');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    }
}
