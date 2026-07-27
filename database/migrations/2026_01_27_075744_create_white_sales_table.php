<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date');
            $table->time('sale_time');
            $table->string('invoice_no')->unique();
            $table->string('mobile_no')->nullable();
            $table->string('company_name')->nullable();
            $table->string('proprietor_name')->nullable();
            $table->unsignedBigInteger('shift_id');
            $table->decimal('total_amount', 10, 2);
            $table->text('remarks')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('is_send_sms')->default(false);
            $table->timestamps();

            $table->foreign('shift_id')->references('id')->on('shifts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_sales');
    }
};
