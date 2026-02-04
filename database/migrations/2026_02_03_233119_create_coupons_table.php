<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->enum('type', ['fixed', 'percentage']);
            $table->decimal('value', 12, 2);
            $table->decimal('minimum_amount', 12, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
            $table->index('expires_at');
        });

        Schema::create('coupon_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamp('used_at');
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
            $table->index('order_id');
        });

        Schema::create('cart_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cart_id', 'coupon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_coupons');
        Schema::dropIfExists('coupon_usage');
        Schema::dropIfExists('coupons');
    }
};
