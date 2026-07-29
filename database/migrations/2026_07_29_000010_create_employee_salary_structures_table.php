<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->unique()
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->decimal('basic_salary', 24, 4);
            $table->decimal('home_rent_percent', 8, 4)->default(0);
            $table->decimal('home_rent_amount', 24, 4)->default(0);
            $table->decimal('medical_percent', 8, 4)->default(0);
            $table->decimal('medical_amount', 24, 4)->default(0);
            $table->decimal('conveyance_percent', 8, 4)->default(0);
            $table->decimal('conveyance_amount', 24, 4)->default(0);
            $table->decimal('other_allowances', 24, 4)->default(0);
            $table->decimal('deductions', 24, 4)->default(0);
            $table->decimal('gross_salary', 24, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_structures');
    }
};
