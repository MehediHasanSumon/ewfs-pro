<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->string('payroll_code', 40)
                ->nullable()
                ->unique('payroll_periods_payroll_code_unique')
                ->after('id');
            $table->text('remarks')->nullable()->after('year');
            $table->timestamp('generated_at')->nullable()->after('started_at');
            $table->timestamp('cancelled_at')->nullable()->after('locked_at');
        });

        Schema::table('payroll_snapshots', function (Blueprint $table): void {
            $table->decimal('monthly_salary', 24, 4)
                ->nullable()
                ->after('basic_salary');
        });

        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->decimal('monthly_salary', 24, 4)
                ->nullable()
                ->after('employee_id');
            $table->decimal('total_deduction', 24, 4)
                ->default(0)
                ->after('net_salary');
            $table->decimal('total_bonus', 24, 4)
                ->default(0)
                ->after('total_deduction');
            $table->decimal('salary_payable', 24, 4)
                ->default(0)
                ->after('advance_applied');
        });

        DB::table('payroll_periods')
            ->where('status', 'processing')
            ->update([
                'status' => 'generated',
                'generated_at' => DB::raw('COALESCE(started_at, updated_at)'),
            ]);
        DB::table('payroll_periods')
            ->whereIn('status', ['completed', 'locked'])
            ->update(['status' => 'paid']);

        DB::table('payroll_periods')
            ->whereNull('payroll_code')
            ->orderBy('id')
            ->get(['id', 'month', 'year'])
            ->each(function (object $period): void {
                DB::table('payroll_periods')
                    ->where('id', $period->id)
                    ->update([
                        'payroll_code' => sprintf(
                            'PR-%04d%02d-%06d',
                            $period->year,
                            $period->month,
                            $period->id
                        ),
                    ]);
            });

        DB::table('payroll_snapshots')->update([
            'monthly_salary' => DB::raw('net_salary'),
        ]);
        DB::table('payroll_items')->update([
            'monthly_salary' => DB::raw('net_salary'),
            'total_deduction' => 0,
            'total_bonus' => 0,
            'salary_payable' => DB::raw('net_payable'),
        ]);
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->dropColumn([
                'monthly_salary',
                'total_deduction',
                'total_bonus',
                'salary_payable',
            ]);
        });

        Schema::table('payroll_snapshots', function (Blueprint $table): void {
            $table->dropColumn('monthly_salary');
        });

        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->dropUnique('payroll_periods_payroll_code_unique');
            $table->dropColumn([
                'payroll_code',
                'remarks',
                'generated_at',
                'cancelled_at',
            ]);
        });
    }
};
