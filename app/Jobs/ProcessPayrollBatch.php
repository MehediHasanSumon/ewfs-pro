<?php

namespace App\Jobs;

use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;

class ProcessPayrollBatch implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $payrollPeriodId,
        public readonly array $data,
        public readonly ?int $userId
    ) {}

    public function uniqueId(): string
    {
        $employeeIds = collect($this->data['employee_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->implode('-');

        return $this->payrollPeriodId.':'.$employeeIds;
    }

    public function handle(PayrollService $payroll): void
    {
        Auth::forgetGuards();

        try {
            if ($this->userId !== null) {
                Auth::onceUsingId($this->userId);
            }

            $payroll->process(
                PayrollPeriod::query()->findOrFail($this->payrollPeriodId),
                $this->data
            );
        } finally {
            Auth::forgetGuards();
        }
    }
}
