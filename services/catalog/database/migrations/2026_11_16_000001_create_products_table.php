<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku', 64)->unique();
            $table->text('description')->nullable();
            // Money is stored in minor units (cents) to avoid floating point errors.
            $table->unsignedBigInteger('price_amount');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'created_at']);
            $table->index(['is_active', 'price_amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
