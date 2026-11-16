<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasUuids;

    protected $fillable = ['product_id', 'name', 'sku', 'unit_price_amount', 'quantity', 'line_total_amount'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['unit_price_amount' => 'integer', 'quantity' => 'integer', 'line_total_amount' => 'integer'];
    }
}
