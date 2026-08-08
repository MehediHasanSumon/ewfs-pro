<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_voucher_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_item_id')
                ->nullable()
                ->constrained('payroll_items')
                ->cascadeOnDelete();
            $table->foreignId('payroll_extra_id')
                ->nullable()
                ->constrained('payroll_extras')
                ->cascadeOnDelete();
            $table->foreignId('voucher_id')
                ->unique('payroll_voucher_links_voucher_unique')
                ->constrained('vouchers')
                ->restrictOnDelete();
            $table->string('role', 30);
            $table->string('status', 20)->default('posted');
            $table->timestamps();
            $table->index(['payroll_item_id', 'role', 'status']);
            $table->index(['payroll_extra_id', 'role', 'status']);
        });

        DB::table('payroll_items')
            ->whereNotNull('payment_voucher_id')
            ->get(['id', 'payment_voucher_id'])
            ->each(function (object $item): void {
                DB::table('payroll_voucher_links')->insertOrIgnore([
                    'payroll_item_id' => $item->id,
                    'voucher_id' => $item->payment_voucher_id,
                    'role' => 'salary',
                    'status' => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('payroll_items')
            ->whereNotNull('advance_adjustment_voucher_id')
            ->get(['id', 'advance_adjustment_voucher_id'])
            ->each(function (object $item): void {
                DB::table('payroll_voucher_links')->insertOrIgnore([
                    'payroll_item_id' => $item->id,
                    'voucher_id' => $item->advance_adjustment_voucher_id,
                    'role' => 'advance_adjustment',
                    'status' => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_voucher_links');
    }
};
