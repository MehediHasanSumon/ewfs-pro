<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shift_closings')) {
            Schema::table('shift_closings', function (Blueprint $table) {
                if (! Schema::hasColumn('shift_closings', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable()->after('closed_at');
                }
                if (! Schema::hasColumn('shift_closings', 'reversed_by')) {
                    $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
                }
            });

            // If MySQL, update the unique constraint and trigger
            if (DB::getDriverName() === 'mysql') {
                // Drop the strict unique constraint on (business_date, shift_id) to allow re-closing after reversal
                try {
                    DB::statement('ALTER TABLE shift_closings DROP INDEX shift_closings_business_date_shift_id_unique');
                } catch (\Throwable) {
                    // Index may have a different name or already dropped
                }

                try {
                    DB::statement('ALTER TABLE shift_closings ADD INDEX sc_business_date_shift_id_idx (business_date, shift_id)');
                } catch (\Throwable) {
                    // Index already exists
                }

                // Drop and recreate shift_closings_before_update trigger to allow transitioning from posted to reversed
                DB::unprepared('DROP TRIGGER IF EXISTS shift_closings_before_update');

                DB::unprepared("
                    CREATE TRIGGER shift_closings_before_update
                    BEFORE UPDATE ON shift_closings
                    FOR EACH ROW
                    BEGIN
                        IF OLD.status = 'reversed' THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reversed shift closings are immutable';
                        END IF;

                        IF OLD.status = 'posted' AND NEW.status <> 'reversed' THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted shift closings can only be transitioned to reversed';
                        END IF;

                        IF NEW.status = 'posted' AND OLD.status <> 'posted' THEN
                            SET NEW.closed_at = COALESCE(NEW.closed_at, CURRENT_TIMESTAMP);
                            SET NEW.variance_amount = NEW.expected_cash - NEW.actual_cash;
                        END IF;

                        IF NEW.status = 'reversed' AND OLD.status = 'posted' THEN
                            SET NEW.reversed_at = COALESCE(NEW.reversed_at, CURRENT_TIMESTAMP);
                        END IF;
                    END
                ");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shift_closings')) {
            if (DB::getDriverName() === 'mysql') {
                DB::unprepared('DROP TRIGGER IF EXISTS shift_closings_before_update');

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
            }

            Schema::table('shift_closings', function (Blueprint $table) {
                if (Schema::hasColumn('shift_closings', 'reversed_by')) {
                    $table->dropForeign(['reversed_by']);
                    $table->dropColumn('reversed_by');
                }
                if (Schema::hasColumn('shift_closings', 'reversed_at')) {
                    $table->dropColumn('reversed_at');
                }
            });
        }
    }
};
