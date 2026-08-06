<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setStatus(false, false);
    }

    public function down(): void
    {
        $this->setStatus(true, true);
    }

    private function setStatus(bool $status, bool $isSystem): void
    {
        if (
            ! Schema::hasTable('voucher_categories')
            || ! Schema::hasTable('voucher_transaction_types')
        ) {
            return;
        }

        $categoryQuery = DB::table('voucher_categories');

        if (Schema::hasColumn('voucher_categories', 'code')) {
            $categoryQuery->where('code', 'VC001');
        } else {
            $categoryQuery->where('name', 'Customer');
        }

        $categoryId = $categoryQuery->value('id');

        if (! $categoryId) {
            return;
        }

        $updates = ['status' => $status];

        if (Schema::hasColumn('voucher_transaction_types', 'is_system')) {
            $updates['is_system'] = $isSystem;
        }

        if (Schema::hasColumn('voucher_transaction_types', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('voucher_transaction_types')
            ->where('voucher_category_id', $categoryId)
            ->where('code', '1033')
            ->where('voucher_type', 'receipt')
            ->update($updates);
    }
};
