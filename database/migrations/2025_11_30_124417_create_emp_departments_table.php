<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emp_type_id')->nullable()->constrained('emp_types')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['emp_type_id', 'name']);
            $table->index(['status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_departments');
    }
};
