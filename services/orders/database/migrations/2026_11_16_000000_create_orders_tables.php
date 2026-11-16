<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Users live in the auth service: this is a reference, not a foreign key.
            $table->uuid('user_id');
            $table->string('status', 20);
            $table->unsignedBigInteger('total_amount');
            $table->char('currency', 3);
            $table->string('cancellation_reason', 50)->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            // Product data is copied at checkout so later catalog edits do not rewrite history.
            $table->uuid('product_id');
            $table->string('name');
            $table->string('sku', 64);
            $table->unsignedBigInteger('unit_price_amount');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
