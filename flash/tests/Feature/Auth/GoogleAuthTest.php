<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_a_user_with_a_generated_password(): void
    {
        $defaultRole = Role::create([
            'name' => 'User',
            'slug' => 'user',
            'description' => 'Standard platform user',
            'is_default' => true,
        ]);

        $googleUser = (new SocialiteUser())
            ->setRaw([
                'sub' => 'google-123',
                'email' => 'ahmedekhwat@gmail.com',
                'email_verified' => true,
                'name' => 'Ahmed Ekhwat',
                'picture' => 'https://example.com/avatar.jpg',
            ])
            ->map([
                'id' => 'google-123',
                'name' => 'Ahmed Ekhwat',
                'email' => 'ahmedekhwat@gmail.com',
                'avatar' => 'https://example.com/avatar.jpg',
            ]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')
            ->once()
            ->andReturnSelf();
        $provider->shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $user = User::query()->where('email', 'ahmedekhwat@gmail.com')->first();

        $response->assertRedirect();
        $this->assertNotNull($user);
        $this->assertNotSame('', (string) $user->password);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('users', [
            'email' => 'ahmedekhwat@gmail.com',
            'google_id' => 'google-123',
            'provider' => 'google',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $defaultRole->id,
        ]);
    }
}