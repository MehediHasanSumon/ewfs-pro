<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code', 64)->nullable()->unique();
            $table->string('url', 500);
            $table->text('api_key');
            $table->string('sender_id', 100);
            $table->unsignedSmallInteger('priority')->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_settings');
    }
};
