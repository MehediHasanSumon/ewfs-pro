<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained()->onDelete('cascade');
            $table->string('code', 150)->nullable();
            $table->string('name', 150)->nullable();
            $table->string('mobile', 100)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('nid_number', 100)->nullable();
            $table->string('vat_reg_no', 100)->nullable();
            $table->string('tin_no', 100)->nullable();
            $table->string('trade_license', 100)->nullable();
            $table->decimal('discount_rate', 18, 2)->default(0.00);
            $table->decimal('security_deposit', 18, 2)->default(0.00);
            $table->decimal('credit_limit', 18, 2)->default(0.00);
            $table->longText('address')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
