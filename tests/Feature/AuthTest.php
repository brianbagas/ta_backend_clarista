<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles required for factory
        Role::create(['role' => 'owner']);
        Role::create(['role' => 'customer']);
    }

    /** @test */
    public function customer_can_register_successfully()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => 'password',
            'no_hp' => '081234567890',
            'gender' => 'pria',
        ]);

        $response->assertStatus(201) // Or 200 depending on controller
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'name',
                    'email',
                    'role'
                ]
            ]);

        $this->assertDatabaseHas('users', ['email' => 'customer@test.com']);
    }

    /** @test */
    public function register_validation_fails_with_invalid_data()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'p', // Invalid
            'email' => 'not-an-email@email', // Invalid
            'password' => 'short', // Invalid if min:8
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->customer()->create([
            'email' => 'login@test.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                ]
            ]);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        $user = User::factory()->customer()->create([
            'email' => 'login@test.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function customer_cannot_access_owner_endpoint()
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer)
            ->getJson('/api/laporan');

        $response->assertStatus(403);
    }
}
