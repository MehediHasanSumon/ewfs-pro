<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('emp_type_id')->nullable()->constrained('emp_types')->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained('emp_departments')->onDelete('set null');
            $table->foreignId('designation_id')->nullable()->constrained('emp_designations')->onDelete('set null');
            $table->string('employee_code', 50)->nullable();
            $table->string('employee_name', 100)->nullable();
            $table->string('email', 100)->nullable();
            $table->integer('order')->default(1);
            $table->date('dob')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('emergency_contact_person', 100)->nullable();
            $table->string('religion', 100)->nullable();
            $table->string('nid', 100)->nullable();
            $table->string('mobile', 100)->nullable();
            $table->string('mobile_two', 20)->nullable();
            $table->string('emergency_contact_number', 100)->nullable();
            $table->string('father_name', 100)->nullable();
            $table->string('mother_name', 100)->nullable();
            $table->string('present_address', 250)->nullable();
            $table->string('permanent_address', 350)->nullable();
            $table->string('job_status', 50)->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->date('joining_date')->nullable();
            $table->boolean('status')->default(true);
            $table->date('status_date')->nullable();
            $table->string('photo', 255)->nullable();
            $table->string('signature', 255)->nullable();
            $table->string('highest_education', 100)->nullable();
            $table->string('reference_one_name', 300)->nullable();
            $table->string('reference_one_phone', 150)->nullable();
            $table->string('reference_one_address', 300)->nullable();
            $table->string('reference_two_name', 300)->nullable();
            $table->string('reference_two_phone', 150)->nullable();
            $table->string('reference_two_address', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
