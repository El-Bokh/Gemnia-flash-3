<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_token(): void
    {
        $defaultRole = Role::create([
            'name'        => 'User',
            'slug'        => 'user',
            'description' => 'Standard platform user',
            'is_default'  => true,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Jane Example',
            'email'                 => 'JANE@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account created successfully.')
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.user.roles.0', 'user');

        $this->assertDatabaseHas('users', [
            'name'   => 'Jane Example',
            'email'  => 'jane@example.com',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('role_user', [
            'role_id' => $defaultRole->id,
        ]);
    }

    public function test_registered_user_can_log_in_with_the_same_credentials(): void
    {
        Role::create([
            'name'        => 'User',
            'slug'        => 'user',
            'description' => 'Standard platform user',
            'is_default'  => true,
        ]);

        $this->postJson('/api/auth/register', [
            'name'                  => 'John Example',
            'email'                 => 'john@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertCreated();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'john@example.com',
            'password' => 'Password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'john@example.com')
            ->assertJsonPath('data.user.roles.0', 'user');
    }
}