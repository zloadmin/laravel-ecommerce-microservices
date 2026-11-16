<?php

namespace App\Console\Commands;

use App\Models\Order;
use Ecommerce\Shared\Messaging\Outbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending {--minutes= : Override orders.pending_timeout_minutes}';

    protected $description = 'Cancel orders that have waited too long for a stock answer';

    public function handle(): int
    {
        $minutes = (int) ($this->option('minutes') ?? config('orders.pending_timeout_minutes'));
        $expired = 0;

        Order::where('status', Order::PENDING)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->select('id')
            ->chunkById(100, function ($orders) use (&$expired): void {
                foreach ($orders as $candidate) {
                    // One transaction per order; re-check under lock because the
                    // stock answer may arrive at this very moment.
                    $expired += DB::transaction(function () use ($candidate): int {
                        $order = Order::whereKey($candidate->id)->lockForUpdate()->first();

                        if ($order === null || $order->status !== Order::PENDING) {
                            return 0;
                        }

                        $order->update(['status' => Order::CANCELLED, 'cancellation_reason' => 'expired']);

                        // If the catalog reserves stock later, this makes it release it again.
                        Outbox::record('order.cancelled', ['order_id' => $order->id, 'user_id' => $order->user_id, 'reason' => 'expired']);

                        return 1;
                    });
                }
            });

        if ($expired > 0) {
            $this->components->info("Expired $expired pending order(s).");
        }

        return self::SUCCESS;
    }
}
