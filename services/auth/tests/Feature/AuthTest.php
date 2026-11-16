<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use Ecommerce\Shared\Auth\JwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_a_customer_and_returns_verifiable_tokens(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => '  Ada@Example.com ',
            'password' => 'secret-password',
            'role' => 'admin', // must be ignored
        ])->assertCreated()
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password');

        $identity = app(JwtVerifier::class)->verify($response->json('data.access_token'));

        $this->assertSame($response->json('data.user.id'), $identity->id);
        $this->assertSame('customer', $identity->role);
        $this->assertDatabaseMissing('refresh_tokens', ['token_hash' => $response->json('data.refresh_token')]);
    }

    public function test_register_validates_input_and_rejects_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', ['name' => 'X', 'email' => 'TAKEN@example.com', 'password' => 'short'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['email', 'password']]]]);
    }

    public function test_login_returns_tokens_for_valid_credentials(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertTrue(app(JwtVerifier::class)->verify($response->json('data.access_token'))->isAdmin());
    }

    public function test_login_rejects_wrong_password_and_unknown_email_identically(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        foreach (['user@example.com', 'nobody@example.com'] as $email) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertStatus(401)
                ->assertJsonPath('error.code', 'invalid_credentials');
        }
    }

    public function test_refresh_rotates_the_token_and_reuse_revokes_every_session(): void
    {
        $user = User::factory()->create();
        $first = $this->login($user);

        $second = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->json('data');

        $this->assertNotSame($first['refresh_token'], $second['refresh_token']);

        // Replaying the rotated token is treated as theft.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_refresh_token');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $second['refresh_token']])->assertStatus(401);
        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
    }

    public function test_refresh_rejects_expired_and_unknown_tokens(): void
    {
        $tokens = $this->login(User::factory()->create());

        $this->travel(31)->days();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])->assertStatus(401);
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'not-a-token'])->assertStatus(401);
    }

    public function test_logout_revokes_the_refresh_token(): void
    {
        $tokens = $this->login(User::factory()->create());

        $this->postJson('/api/v1/auth/logout', ['refresh_token' => $tokens['refresh_token']])->assertNoContent();
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])->assertStatus(401);
    }

    /** @return array<string, mixed> */
    private function login(User $user): array
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->json('data');
    }
}
