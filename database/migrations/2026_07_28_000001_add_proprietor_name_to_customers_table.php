<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('proprietor_name', 255)
                ->nullable()
                ->after('name');
            $table->index('proprietor_name', 'customers_proprietor_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_proprietor_name_idx');
            $table->dropColumn('proprietor_name');
        });
    }
};
