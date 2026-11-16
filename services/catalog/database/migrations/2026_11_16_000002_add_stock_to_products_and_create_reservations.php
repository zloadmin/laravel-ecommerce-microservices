<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('stock')->default(0);
        });

        // One row per order that asked for stock, including the outcome.
        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->uuid('order_id')->primary();
            $table->string('status', 20);
            $table->json('items');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('stock');
        });
    }
};
