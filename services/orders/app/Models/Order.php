<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUuids;

    /** Waiting for the catalog to reserve stock. */
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['user_id', 'status', 'total_amount', 'currency', 'cancellation_reason', 'idempotency_key'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['total_amount' => 'integer'];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::PENDING, self::CONFIRMED], true);
    }
}
