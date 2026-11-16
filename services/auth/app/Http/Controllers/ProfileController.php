<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($this->currentUser($request));
    }

    public function update(Request $request, TokenService $tokens): UserResource
    {
        $user = $this->currentUser($request);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', Password::min(8), 'max:255'],
            'current_password' => ['required_with:password', 'string'],
        ]);

        if (isset($data['password'])) {
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }

            // A password change signs out every other session.
            $tokens->revokeAll($user->id);
        }

        $user->update(collect($data)->only(['name', 'password'])->all());

        return new UserResource($user);
    }

    private function currentUser(Request $request): User
    {
        // A valid token for a deleted account resolves to 404.
        return User::findOrFail($request->user()->id);
    }
}
