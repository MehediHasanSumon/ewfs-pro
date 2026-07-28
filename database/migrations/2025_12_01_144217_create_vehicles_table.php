<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('vehicle_type', 150)->nullable();
            $table->string('vehicle_name', 150)->nullable();
            $table->string('vehicle_number', 50);
            $table->date('reg_date')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['customer_id', 'vehicle_number']);
            $table->index(['vehicle_number', 'id']);
            $table->index(['customer_id', 'status', 'id']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->restrictOnDelete();
            $table->index(['vehicle_id', 'journal_entry_id'], 'jl_vehicle_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropIndex('jl_vehicle_entry_idx');
            $table->dropColumn('vehicle_id');
        });

        Schema::dropIfExists('vehicles');
    }
};
