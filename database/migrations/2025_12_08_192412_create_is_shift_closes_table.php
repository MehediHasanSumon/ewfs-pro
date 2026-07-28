<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            CREATE TRIGGER journal_entries_before_insert_shift_lock
            BEFORE INSERT ON journal_entries
            FOR EACH ROW
            BEGIN
                IF NEW.shift_id IS NOT NULL AND EXISTS (
                    SELECT 1
                    FROM shift_closings
                    WHERE shift_id = NEW.shift_id
                      AND business_date = NEW.business_date
                      AND status = 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Accounting is locked for the closed shift';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER inventory_movements_before_insert_shift_lock
            BEFORE INSERT ON inventory_movements
            FOR EACH ROW
            BEGIN
                IF NEW.shift_id IS NOT NULL AND EXISTS (
                    SELECT 1
                    FROM shift_closings
                    WHERE shift_id = NEW.shift_id
                      AND business_date = NEW.business_date
                      AND status = 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Inventory is locked for the closed shift';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER sales_before_update_shift_lock
            BEFORE UPDATE ON sales
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM shift_closings
                    WHERE shift_id = OLD.shift_id
                      AND business_date = OLD.sale_date
                      AND status = 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sales are locked for the closed shift';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER sales_before_delete_shift_lock
            BEFORE DELETE ON sales
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM shift_closings
                    WHERE shift_id = OLD.shift_id
                      AND business_date = OLD.sale_date
                      AND status = 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sales cannot be deleted from a closed shift';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER purchases_before_update_shift_lock
            BEFORE UPDATE ON purchases
            FOR EACH ROW
            BEGIN
                IF OLD.shift_id IS NOT NULL AND EXISTS (
                    SELECT 1 FROM shift_closings
                    WHERE shift_id = OLD.shift_id
                      AND business_date = OLD.purchase_date
                      AND status = 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Purchases are locked for the closed shift';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER purchases_before_delete_shift_lock
            BEFORE DELETE ON purchases
            FOR EACH ROW
            BEGIN
                IF OLD.shift_id IS NOT NULL AND EXISTS (
                    SELECT 1 FROM shift_closings
                    WHERE shift_id = OLD.shift_id
                      AND business_date = OLD.purchase_date
                      AND status = 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Purchases cannot be deleted from a closed shift';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER credit_sales_before_update_shift_lock
            BEFORE UPDATE ON credit_sales
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM shift_closings
                    WHERE shift_id = OLD.shift_id
                      AND business_date = OLD.sale_date
                      AND status = 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Credit sales are locked for the closed shift';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER credit_sales_before_delete_shift_lock
            BEFORE DELETE ON credit_sales
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM shift_closings
                    WHERE shift_id = OLD.shift_id
                      AND business_date = OLD.sale_date
                      AND status = 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Credit sales cannot be deleted from a closed shift';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS credit_sales_before_delete_shift_lock');
        DB::unprepared('DROP TRIGGER IF EXISTS credit_sales_before_update_shift_lock');
        DB::unprepared('DROP TRIGGER IF EXISTS purchases_before_delete_shift_lock');
        DB::unprepared('DROP TRIGGER IF EXISTS purchases_before_update_shift_lock');
        DB::unprepared('DROP TRIGGER IF EXISTS sales_before_delete_shift_lock');
        DB::unprepared('DROP TRIGGER IF EXISTS sales_before_update_shift_lock');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_before_insert_shift_lock');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_entries_before_insert_shift_lock');
    }
};
