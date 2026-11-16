<?php

namespace Ecommerce\Shared\Tests;

use Ecommerce\Shared\Auth\InvalidTokenException;
use Ecommerce\Shared\Auth\JwtVerifier;
use Ecommerce\Shared\Http\ApiExceptionRenderer;
use Ecommerce\Shared\Testing\JwtTestKeys;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

class JwtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(ExceptionHandler::class)->renderable(fn (Throwable $e) => ApiExceptionRenderer::render($e));
    }

    protected function defineRoutes($router): void
    {
        $router->get('private', fn (Request $request) => ['id' => $request->user()->id])->middleware('jwt');
        $router->get('optional', fn (Request $request) => ['guest' => $request->user() === null])->middleware('jwt:optional');
        $router->get('admin', fn () => ['ok' => true])->middleware(['jwt', 'role:admin']);
    }

    public function test_valid_token_resolves_the_caller(): void
    {
        $user = $this->app->make(JwtVerifier::class)->verify(JwtTestKeys::token('user-1', 'admin'));

        $this->assertSame('user-1', $user->id);
        $this->assertTrue($user->isAdmin());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function badClaims(): array
    {
        return [
            'expired' => [['exp' => time() - 10]],
            'wrong issuer' => [['iss' => 'someone-else']],
            'empty subject' => [['sub' => '']],
            'not yet valid' => [['nbf' => time() + 3600]],
        ];
    }

    /** @param  array<string, mixed>  $claims */
    #[DataProvider('badClaims')]
    public function test_bad_claims_are_rejected(array $claims): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->app->make(JwtVerifier::class)->verify(JwtTestKeys::token(claims: $claims));
    }

    public function test_token_signed_with_another_key_is_rejected(): void
    {
        $token = JwtTestKeys::token();
        [$header, $payload] = explode('.', $token);

        $this->expectException(InvalidTokenException::class);

        $this->app->make(JwtVerifier::class)->verify("$header.$payload.".rtrim(strtr(base64_encode('forged'), '+/', '-_'), '='));
    }

    public function test_required_middleware_enforces_a_token(): void
    {
        $this->getJson('private')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
        $this->withToken('garbage')->getJson('private')->assertStatus(401);
        $this->withToken(JwtTestKeys::token('user-9'))->getJson('private')->assertOk()->assertJson(['id' => 'user-9']);
    }

    public function test_optional_middleware_allows_guests_but_not_bad_tokens(): void
    {
        $this->getJson('optional')->assertOk()->assertJson(['guest' => true]);
        $this->withToken(JwtTestKeys::token())->getJson('optional')->assertOk()->assertJson(['guest' => false]);
        $this->withToken('garbage')->getJson('optional')->assertStatus(401);
    }

    public function test_role_middleware_restricts_by_role(): void
    {
        $this->withToken(JwtTestKeys::token())->getJson('admin')->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        $this->withToken(JwtTestKeys::token(role: 'admin'))->getJson('admin')->assertOk();
    }
}
