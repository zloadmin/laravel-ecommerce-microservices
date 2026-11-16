<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    public const RESERVED = 'reserved';

    public const REJECTED = 'rejected';

    public const RELEASED = 'released';

    protected $primaryKey = 'order_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['order_id', 'status', 'items'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['items' => 'array'];
    }
}
