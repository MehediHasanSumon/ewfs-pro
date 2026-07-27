<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->decimal('credit_sales', 18, 2)->default(0);
            $table->decimal('bank_sales', 18, 2)->default(0);
            $table->decimal('cash_sales', 18, 2)->default(0);
            $table->decimal('credit_sales_other', 18, 2)->default(0);
            $table->decimal('bank_sales_other', 18, 2)->default(0);
            $table->decimal('cash_sales_other', 18, 2)->default(0);
            $table->decimal('cash_receive', 18, 2)->default(0);
            $table->decimal('bank_receive', 18, 2)->default(0);
            $table->decimal('total_cash', 18, 2)->default(0);
            $table->decimal('cash_payment', 18, 2)->default(0);
            $table->decimal('bank_payment', 18, 2)->default(0);
            $table->decimal('office_payment', 18, 2)->default(0);
            $table->decimal('final_due_amount', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_readings');
    }
};
