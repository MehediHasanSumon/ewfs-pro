<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispenser_readings', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date')->nullable();
            $table->foreignId('shift_id')->nullable()->default(0)->constrained('shifts')->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('cascade');
            $table->foreignId('dispenser_id')->nullable()->constrained('dispensers')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('cascade');
            $table->decimal('start_reading', 18, 2)->nullable()->default(0.00);
            $table->decimal('end_reading', 18, 2)->nullable()->default(0.00);
            $table->decimal('meter_test', 18, 2)->nullable()->default(0.00);
            $table->decimal('net_reading', 18, 2)->nullable()->default(0.00);
            $table->decimal('item_rate', 18, 2)->nullable()->default(0.00);
            $table->decimal('total_sale', 18, 2)->nullable()->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispenser_readings');
    }
};
