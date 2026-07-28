<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS sales_before_update');
        DB::unprepared("
            CREATE TRIGGER sales_before_update
            BEFORE UPDATE ON sales
            FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft' AND NOT (
                    NEW.status = 'draft'
                    AND NEW.journal_entry_id IS NULL
                    AND OLD.journal_entry_id IS NOT NULL
                    AND (
                        SELECT status
                        FROM journal_entries
                        WHERE id = OLD.journal_entry_id
                    ) = 'reversed'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted sales are immutable';
                END IF;

                IF NEW.status IN ('posted', 'partially_paid', 'paid') AND (
                    NEW.journal_entry_id IS NULL OR
                    (
                        SELECT status
                        FROM journal_entries
                        WHERE id = NEW.journal_entry_id
                    ) <> 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A sale can only post with a posted journal entry';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS sales_before_update');
        DB::unprepared("
            CREATE TRIGGER sales_before_update
            BEFORE UPDATE ON sales
            FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted sales are immutable';
                END IF;

                IF NEW.status = 'posted' AND (
                    NEW.journal_entry_id IS NULL OR
                    (
                        SELECT status
                        FROM journal_entries
                        WHERE id = NEW.journal_entry_id
                    ) <> 'posted'
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A sale can only post with a posted journal entry';
                END IF;
            END
        ");
    }
};
