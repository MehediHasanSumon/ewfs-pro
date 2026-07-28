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
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 150)->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone_number', 50);
            $table->text('message');
            $table->foreignId('sms_template_id')->nullable()->constrained('sms_templates')->onDelete('set null');
            $table->foreignId('sms_setting_id')->nullable()->constrained('sms_settings')->onDelete('set null');
            $table->enum('status', ['queued', 'processing', 'sent', 'failed', 'cancelled'])->default('queued');
            $table->json('response')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at', 'id'], 'sms_status_retry_idx');
            $table->index(['phone_number', 'created_at', 'id'], 'sms_phone_date_idx');
            $table->index(['customer_id', 'created_at', 'id'], 'sms_customer_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
