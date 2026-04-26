<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->enum('gateway', ['paystack', 'bank_transfer', 'cash', 'pos']);
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('NGN');
            $table->json('gateway_response')->nullable();
            $table->string('channel')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
