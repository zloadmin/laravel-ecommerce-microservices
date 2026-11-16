<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_ADMIN = 'admin';

    // "role" is deliberately not fillable: it must never come from request input.
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    protected $attributes = ['role' => self::ROLE_CUSTOMER];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    /** @return HasMany<RefreshToken, $this> */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }
}
