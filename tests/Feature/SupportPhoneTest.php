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
            ->assertJsonCount(1, 'data');
    }
}
