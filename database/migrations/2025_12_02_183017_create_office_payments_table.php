<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 64);
            $table->string('prefix', 32)->nullable();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'fiscal_year']);
        });

        Schema::create('report_bucket_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('bucket_code', 100);
            $table->enum('mapping_type', ['account', 'event_type', 'voucher_type', 'payment_sub_type', 'movement_type']);
            $table->string('mapping_key', 150);
            $table->smallInteger('display_order')->default(0);
            $table->smallInteger('amount_sign')->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['bucket_code', 'mapping_type', 'mapping_key'], 'rbm_bucket_mapping_uk');
            $table->index(['mapping_type', 'mapping_key', 'status'], 'rbm_mapping_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_bucket_mappings');
        Schema::dropIfExists('document_sequences');
    }
};
