<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('company_name');
            $table->text('company_details')->nullable();
            $table->string('proprietor_name')->nullable();
            $table->text('company_address')->nullable();
            $table->text('factory_address')->nullable();
            $table->string('company_mobile')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_email')->nullable();
            $table->string('trade_license')->nullable();
            $table->string('tin_no')->nullable();
            $table->string('bin_no')->nullable();
            $table->string('vat_no')->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->string('company_logo')->nullable();
            $table->boolean('is_registration')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
