<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('sku')->unique()->nullable();
            $table->decimal('base_price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('has_variants')->default(false);
            $table->enum('product_type', ['simple', 'variable'])->default('simple');
            $table->string('thumbnail')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('tags')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_featured']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
