<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'cancellation_reason' => $this->cancellation_reason,
            'total' => ['amount' => $this->total_amount, 'currency' => $this->currency],
            'items' => $this->items->map(fn (OrderItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => ['amount' => $item->unit_price_amount, 'currency' => $this->currency],
                'line_total' => ['amount' => $item->line_total_amount, 'currency' => $this->currency],
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
