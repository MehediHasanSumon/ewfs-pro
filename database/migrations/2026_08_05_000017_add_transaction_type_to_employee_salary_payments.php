<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumn(
                'employee_salary_payments',
                'voucher_transaction_type_id'
            )
        ) {
            Schema::table('employee_salary_payments', function (Blueprint $table): void {
                $table->foreignId('voucher_transaction_type_id')
                    ->nullable()
                    ->after('payment_voucher_id')
                    ->constrained('voucher_transaction_types')
                    ->restrictOnDelete();
            });
        }

        $monthlySalaryTypeId = DB::table('voucher_transaction_types as vtt')
            ->join(
                'voucher_categories as vc',
                'vc.id',
                '=',
                'vtt.voucher_category_id'
            )
            ->where('vc.code', 'VC002')
            ->where('vtt.code', '1001')
            ->value('vtt.id');

        if ($monthlySalaryTypeId !== null) {
            DB::table('employee_salary_payments')
                ->whereNull('voucher_transaction_type_id')
                ->update([
                    'voucher_transaction_type_id' => $monthlySalaryTypeId,
                ]);
        }

        if (
            DB::table('employee_salary_payments')
                ->whereNull('voucher_transaction_type_id')
                ->exists()
        ) {
            throw new \RuntimeException(
                'Existing salary payments could not be linked to the Monthly Salary transaction type.'
            );
        }

        Schema::table('employee_salary_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('voucher_transaction_type_id')
                ->nullable(false)
                ->change();
        });

        if (! $this->hasIndex('employee_salary_payments_employee_id_index')) {
            Schema::table('employee_salary_payments', function (Blueprint $table): void {
                $table->index(
                    'employee_id',
                    'employee_salary_payments_employee_id_index'
                );
            });
        }

        if ($this->hasIndex('employee_salary_payment_period_unique')) {
            Schema::table('employee_salary_payments', function (Blueprint $table): void {
                $table->dropUnique('employee_salary_payment_period_unique');
            });
        }

        if ($this->hasIndex('employee_salary_payment_period_status_idx')) {
            Schema::table('employee_salary_payments', function (Blueprint $table): void {
                $table->dropIndex(
                    'employee_salary_payment_period_status_idx'
                );
            });
        }

        if (! $this->hasIndex('employee_salary_payment_period_type_unique')) {
            Schema::table('employee_salary_payments', function (Blueprint $table): void {
                $table->unique(
                    [
                        'employee_id',
                        'salary_year',
                        'salary_month',
                        'voucher_transaction_type_id',
                    ],
                    'employee_salary_payment_period_type_unique'
                );
            });
        }

        if (
            ! $this->hasIndex(
                'employee_salary_payment_period_type_status_idx'
            )
        ) {
            Schema::table('employee_salary_payments', function (Blueprint $table): void {
                $table->index(
                    [
                        'salary_year',
                        'salary_month',
                        'voucher_transaction_type_id',
                        'status',
                        'employee_id',
                    ],
                    'employee_salary_payment_period_type_status_idx'
                );
            });
        }
    }

    public function down(): void
    {
        $hasMultipleTypesPerPeriod = DB::table('employee_salary_payments')
            ->select([
                'employee_id',
                'salary_year',
                'salary_month',
            ])
            ->groupBy([
                'employee_id',
                'salary_year',
                'salary_month',
            ])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleTypesPerPeriod) {
            throw new \RuntimeException(
                'Cannot remove salary payment transaction types while multiple payment types exist for the same employee period.'
            );
        }

        Schema::table('employee_salary_payments', function (Blueprint $table): void {
            $table->dropUnique('employee_salary_payment_period_type_unique');
            $table->dropIndex(
                'employee_salary_payment_period_type_status_idx'
            );
            $table->dropConstrainedForeignId('voucher_transaction_type_id');

            $table->unique(
                ['employee_id', 'salary_year', 'salary_month'],
                'employee_salary_payment_period_unique'
            );
            $table->index(
                ['salary_year', 'salary_month', 'status', 'employee_id'],
                'employee_salary_payment_period_status_idx'
            );
        });

        if ($this->hasIndex('employee_salary_payments_employee_id_index')) {
            Schema::table('employee_salary_payments', function (Blueprint $table): void {
                $table->dropIndex(
                    'employee_salary_payments_employee_id_index'
                );
            });
        }
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('employee_salary_payments'))
            ->contains(
                fn (array $index): bool => ($index['name'] ?? null) === $name
            );
    }
};
