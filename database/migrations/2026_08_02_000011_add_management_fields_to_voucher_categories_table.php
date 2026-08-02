<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('voucher_categories', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')
                    ->default(0)
                    ->after('status');
                $table->index('sort_order', 'voucher_categories_sort_order_index');
            }

            if (! Schema::hasColumn('voucher_categories', 'is_system')) {
                $table->boolean('is_system')
                    ->default(false)
                    ->after('sort_order');
                $table->index(
                    ['is_system', 'sort_order'],
                    'voucher_categories_system_sort_order_index'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('voucher_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('voucher_categories', 'is_system')) {
                $table->dropIndex('voucher_categories_system_sort_order_index');
                $table->dropColumn('is_system');
            }

            if (Schema::hasColumn('voucher_categories', 'sort_order')) {
                $table->dropIndex('voucher_categories_sort_order_index');
                $table->dropColumn('sort_order');
            }
        });
    }
};
