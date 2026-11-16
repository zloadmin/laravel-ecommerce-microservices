<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Events waiting to be relayed to the broker. Rows are written in the
        // same transaction as the business change that caused them.
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->text('body');
            $table->timestamp('created_at');
            $table->timestamp('published_at')->nullable()->index();
        });

        // Ids of events already handled, so redeliveries are ignored.
        Schema::create('processed_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_messages');
        Schema::dropIfExists('outbox_messages');
    }
};
