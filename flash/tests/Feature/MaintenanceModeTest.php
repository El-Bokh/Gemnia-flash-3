<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_status_reports_active_maintenance(): void
    {
        $this->enableMaintenance();

        $this->getJson('/api/maintenance/status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.message', 'Maintenance in progress.')
            ->assertJsonPath('data.can_bypass', false);
    }

    public function test_non_admin_authenticated_user_is_blocked_during_maintenance(): void
    {
        $this->enableMaintenance();

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Maintenance in progress.')
            ->assertJsonPath('data.can_bypass', false);
    }

    public function test_admin_authenticated_user_can_bypass_maintenance(): void
    {
        $this->enableMaintenance();

        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator',
            'is_default' => false,
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $admin->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/maintenance/status')
            ->assertOk()
            ->assertJsonPath('data.can_bypass', true);
    }

    public function test_login_is_blocked_for_non_admin_users_during_maintenance(): void
    {
        $this->enableMaintenance();

        $user = User::factory()->create([
            'email' => 'client@example.com',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Maintenance in progress.');
    }

    private function enableMaintenance(): void
    {
        Setting::create([
            'group' => 'maintenance',
            'key' => 'maintenance_mode',
            'value' => '1',
            'type' => 'boolean',
        ]);

        Setting::create([
            'group' => 'maintenance',
            'key' => 'maintenance_message',
            'value' => 'Maintenance in progress.',
            'type' => 'text',
        ]);

        Setting::create([
            'group' => 'maintenance',
            'key' => 'maintenance_allowed_ips',
            'value' => '[]',
            'type' => 'json',
        ]);

        Setting::clearCache();
    }
}