<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_deductions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_item_id')
                ->constrained('payroll_items')
                ->cascadeOnDelete();
            $table->decimal('amount', 24, 4);
            $table->string('reason', 500);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->index(['payroll_item_id', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_deductions');
    }
};
