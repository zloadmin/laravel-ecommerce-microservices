<?php

namespace Tests\Feature;

use App\Models\User;
use Ecommerce\Shared\Testing\JwtTestKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_a_valid_token(): void
    {
        $this->getJson('/api/v1/users/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');

        $this->withToken('garbage')->getJson('/api/v1/users/me')->assertStatus(401);

        $expired = JwtTestKeys::token(claims: ['exp' => time() - 60]);
        $this->withToken($expired)->getJson('/api/v1/users/me')->assertStatus(401);

        $foreign = JwtTestKeys::token(claims: ['iss' => 'someone-else']);
        $this->withToken($foreign)->getJson('/api/v1/users/me')->assertStatus(401);
    }

    public function test_user_can_read_their_profile(): void
    {
        $user = User::factory()->create();

        $this->withToken(JwtTestKeys::token($user->id))
            ->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_token_of_a_deleted_user_gets_not_found(): void
    {
        $this->withToken(JwtTestKeys::token())->getJson('/api/v1/users/me')->assertNotFound();
    }

    public function test_user_can_update_their_name_but_not_their_role(): void
    {
        $user = User::factory()->create();

        $this->withToken(JwtTestKeys::token($user->id))
            ->patchJson('/api/v1/users/me', ['name' => 'New Name', 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.role', 'customer');
    }

    public function test_password_change_requires_the_current_password_and_revokes_sessions(): void
    {
        $user = User::factory()->create();
        $refreshToken = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->json('data.refresh_token');
        $token = JwtTestKeys::token($user->id);

        $this->withToken($token)
            ->patchJson('/api/v1/users/me', ['password' => 'new-password-1', 'current_password' => 'wrong'])
            ->assertStatus(422);

        $this->withToken($token)
            ->patchJson('/api/v1/users/me', ['password' => 'new-password-1', 'current_password' => 'password'])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password-1', $user->refresh()->password));
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken])->assertStatus(401);
    }
}
