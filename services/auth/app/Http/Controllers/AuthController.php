<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TokenService;
use Ecommerce\Shared\Http\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    public function register(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8), 'max:255'],
        ]);

        $user = User::create($data);

        return $this->session($user, $this->tokens->issue($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Same response for an unknown email and a wrong password.
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            return ApiError::response('invalid_credentials', 'The email or password is incorrect.', 401);
        }

        return $this->session($user, $this->tokens->issue($user));
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);

        $rotated = $this->tokens->rotate($data['refresh_token']);

        if ($rotated === null) {
            return ApiError::response('invalid_refresh_token', 'The refresh token is invalid or expired.', 401);
        }

        return $this->session(...$rotated);
    }

    public function logout(Request $request): Response
    {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);

        $this->tokens->revoke($data['refresh_token']);

        return response()->noContent();
    }

    /** @param  array<string, mixed>  $tokens */
    private function session(User $user, array $tokens, int $status = 200): JsonResponse
    {
        return response()->json(['data' => ['user' => new UserResource($user), ...$tokens]], $status);
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
    }
}
