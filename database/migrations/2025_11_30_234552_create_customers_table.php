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
            $table->foreignId('account_id')->unique()->constrained('accounts')->restrictOnDelete();
            $table->string('code', 64)->nullable()->unique();
            $table->string('name', 150)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('nid_number', 100)->nullable();
            $table->string('vat_reg_no', 100)->nullable();
            $table->string('tin_no', 100)->nullable();
            $table->string('trade_license', 100)->nullable();
            $table->decimal('discount_rate', 7, 4)->default(0);
            $table->decimal('credit_limit', 24, 4)->default(0);
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->longText('address')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['status', 'name', 'id']);
            $table->index(['mobile', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
