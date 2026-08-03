<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('before_stock', 24, 6)
                ->nullable()
                ->after('quantity_out');
            $table->decimal('after_stock', 24, 6)
                ->nullable()
                ->after('before_stock');
            $table->text('remarks')
                ->nullable()
                ->after('source_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn([
                'before_stock',
                'after_stock',
                'remarks',
            ]);
        });
    }
};
