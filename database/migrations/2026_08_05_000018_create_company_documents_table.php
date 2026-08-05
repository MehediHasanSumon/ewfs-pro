<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_setting_id')
                ->constrained('company_settings')
                ->cascadeOnDelete();
            $table->string('document_name');
            $table->string('document_type', 20);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('file_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remarks')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('company_setting_id');
            $table->index('document_type');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['company_setting_id', 'status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents');
    }
};
