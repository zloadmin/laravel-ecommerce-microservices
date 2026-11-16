<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Checkout;
use Ecommerce\Shared\Http\ApiError;
use Ecommerce\Shared\Messaging\Outbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in([Order::PENDING, Order::CONFIRMED, Order::CANCELLED])],
            'user_id' => ['sometimes', 'uuid'], // honoured for admins only
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = $this->visibleTo($request)
            ->with('items')
            ->when($request->user()->isAdmin() && isset($filters['user_id']), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function show(Request $request, string $order): OrderResource
    {
        return new OrderResource($this->visibleTo($request)->with('items')->findOrFail($order));
    }

    public function store(Request $request, Checkout $checkout): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.config('orders.max_lines')],
            'items.*.product_id' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('orders.max_quantity')],
        ]);

        $key = $request->header('Idempotency-Key');

        if ($key !== null && ! preg_match('/^[\w\-]{8,100}$/', $key)) {
            return ApiError::response('invalid_idempotency_key', 'Idempotency-Key must be 8-100 letters, digits, dashes or underscores.', 400);
        }

        try {
            [$order, $created] = $checkout->place($request->user()->id, $data['items'], $key);
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Catalog unavailable during checkout.', ['error' => $e->getMessage()]);

            return ApiError::response('service_unavailable', 'The catalog is unavailable. Please try again.', 503);
        }

        // 201 for a new order; 200 when the Idempotency-Key replays an earlier one.
        return (new OrderResource($order))->response()->setStatusCode($created ? 201 : 200);
    }

    public function cancel(Request $request, string $order): OrderResource|JsonResponse
    {
        return DB::transaction(function () use ($request, $order): OrderResource|JsonResponse {
            $found = $this->visibleTo($request)->lockForUpdate()->findOrFail($order);

            if (! $found->isCancellable()) {
                return ApiError::response('order_not_cancellable', "An order that is {$found->status} cannot be cancelled.", 409);
            }

            $reason = $found->user_id === $request->user()->id ? 'cancelled_by_customer' : 'cancelled_by_admin';
            $found->update(['status' => Order::CANCELLED, 'cancellation_reason' => $reason]);

            // The catalog releases any reserved stock when it sees this event.
            Outbox::record('order.cancelled', ['order_id' => $found->id, 'user_id' => $found->user_id, 'reason' => $reason]);

            return new OrderResource($found->load('items'));
        });
    }

    /**
     * Customers see their own orders; admins see everyone's.
     * Someone else's order therefore looks like it does not exist (404).
     *
     * @return Builder<Order>
     */
    private function visibleTo(Request $request): Builder
    {
        $user = $request->user();

        return Order::query()->when(! $user->isAdmin(), fn (Builder $query) => $query->where('user_id', $user->id));
    }
}
