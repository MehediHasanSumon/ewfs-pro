<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_closings', function (Blueprint $table) {
            $table->id();
            $table->date('business_date');
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->enum('status', ['draft', 'posting', 'posted', 'reversed'])->default('draft');
            $table->decimal('expected_cash', 24, 4)->default(0);
            $table->decimal('actual_cash', 24, 4)->default(0);
            $table->decimal('variance_amount', 24, 4)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('shift_closings')->restrictOnDelete();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['business_date', 'shift_id']);
            $table->index(['status', 'business_date', 'shift_id'], 'sc_status_date_shift_idx');
            $table->index(['shift_id', 'business_date', 'id'], 'sc_shift_date_id_idx');
        });

        Schema::create('dispenser_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_closing_id')->constrained('shift_closings')->cascadeOnDelete();
            $table->foreignId('dispenser_id')->constrained('dispensers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->decimal('start_reading', 24, 6);
            $table->decimal('end_reading', 24, 6);
            $table->decimal('meter_test', 24, 6)->default(0);
            $table->decimal('net_quantity', 24, 6);
            $table->decimal('unit_price', 24, 6);
            $table->decimal('gross_amount', 24, 4);
            $table->foreignId('inventory_movement_id')->nullable()->unique()->constrained('inventory_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['shift_closing_id', 'dispenser_id']);
            $table->index(['product_id', 'shift_closing_id'], 'dr_product_closing_idx');
            $table->index(['employee_id', 'shift_closing_id'], 'dr_employee_closing_idx');
        });

        DB::statement('ALTER TABLE shift_closings ADD CONSTRAINT shift_closing_cash_chk CHECK (expected_cash >= 0 AND actual_cash >= 0)');
        DB::statement('ALTER TABLE dispenser_readings ADD CONSTRAINT dispenser_reading_values_chk CHECK (start_reading >= 0 AND end_reading >= start_reading AND meter_test >= 0 AND net_quantity >= 0 AND unit_price >= 0 AND gross_amount >= 0)');

        DB::unprepared("
            CREATE TRIGGER shift_closings_before_update
            BEFORE UPDATE ON shift_closings
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('posted', 'reversed') THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted shift closings are immutable';
                END IF;

                IF NEW.status = 'posted' AND OLD.status <> 'posted' THEN
                    SET NEW.closed_at = COALESCE(NEW.closed_at, CURRENT_TIMESTAMP);
                    SET NEW.variance_amount = NEW.expected_cash - NEW.actual_cash;
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER shift_closings_before_delete
            BEFORE DELETE ON shift_closings
            FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only draft shift closings can be deleted';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER dispenser_readings_before_update
            BEFORE UPDATE ON dispenser_readings
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM shift_closings WHERE id = OLD.shift_closing_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Closed shift dispenser readings are immutable';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER dispenser_readings_before_delete
            BEFORE DELETE ON dispenser_readings
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM shift_closings WHERE id = OLD.shift_closing_id) <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Closed shift dispenser readings cannot be deleted';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS dispenser_readings_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS dispenser_readings_before_update');
        DB::unprepared('DROP TRIGGER IF EXISTS shift_closings_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS shift_closings_before_update');

        Schema::dropIfExists('dispenser_readings');
        Schema::dropIfExists('shift_closings');
    }
};
