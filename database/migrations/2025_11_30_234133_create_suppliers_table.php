<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained('accounts')->restrictOnDelete();
            $table->string('code', 64)->nullable()->unique();
            $table->string('name', 255);
            $table->string('mobile', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('proprietor_name', 255)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['status', 'name', 'id']);
            $table->index(['mobile', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
