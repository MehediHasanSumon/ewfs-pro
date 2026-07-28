<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->restrictOnDelete();
            $table->string('ac_number', 150)->unique();
            $table->string('name', 150);
            $table->string('semantic_code', 100)->nullable()->unique();
            $table->char('currency', 3)->default('BDT');
            $table->boolean('is_control_account')->default(false);
            $table->boolean('allow_manual_posting')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['group_id', 'status', 'id']);
            $table->index(['name', 'id']);
            $table->index(['status', 'id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
