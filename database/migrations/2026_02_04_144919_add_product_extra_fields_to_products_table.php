<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('sale_price');
            $table->decimal('cost_price', 10, 2)->nullable()->after('compare_at_price');
            $table->string('barcode', 50)->nullable()->after('sku');
            $table->integer('low_stock_threshold')->default(10)->after('stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['compare_at_price', 'cost_price', 'barcode', 'low_stock_threshold']);
        });
    }
};
