<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_products', function (Blueprint $table) {
            $table->index('vehicle_id', 'vehicle_products_vehicle_idx');
            $table->index('product_id', 'vehicle_products_product_idx');
            $table->index(
                ['vehicle_id', 'sort_order'],
                'vehicle_products_vehicle_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_products', function (Blueprint $table) {
            $table->dropIndex('vehicle_products_vehicle_idx');
            $table->dropIndex('vehicle_products_product_idx');
            $table->dropIndex('vehicle_products_vehicle_sort_idx');
        });
    }
};
