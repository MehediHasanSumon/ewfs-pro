<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->foreignId('advance_adjustment_voucher_id')
                ->nullable()
                ->unique()
                ->after('net_payable')
                ->constrained('vouchers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->dropForeign(['advance_adjustment_voucher_id']);
            $table->dropUnique(['advance_adjustment_voucher_id']);
            $table->dropColumn('advance_adjustment_voucher_id');
        });
    }
};
