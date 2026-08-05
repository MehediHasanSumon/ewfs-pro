<?php

use App\Http\Requests\PaymentVoucherRequest;
use App\Http\Requests\ReceivedVoucherRequest;
use App\Http\Requests\SalaryPaymentRequest;
use Tests\TestCase;

uses(TestCase::class);

it('keeps shift optional for payment and received vouchers', function () {
    $paymentShiftRules = (new PaymentVoucherRequest)
        ->rules()['shift_id'];
    $receivedShiftRules = (new ReceivedVoucherRequest)
        ->rules()['shift_id'];

    expect($paymentShiftRules)->toContain('nullable')
        ->and($paymentShiftRules)->not->toContain('required')
        ->and($receivedShiftRules)->toContain('nullable')
        ->and($receivedShiftRules)->not->toContain('required');
});

it('does not expose shift in the salary payment contract', function () {
    $rules = (new SalaryPaymentRequest)->rules();

    expect($rules)->not->toHaveKey('shift_id');
});
