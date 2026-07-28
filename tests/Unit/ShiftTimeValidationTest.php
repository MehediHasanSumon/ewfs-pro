<?php

use App\Http\Requests\ShiftRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

function validateShiftTimes(array $data)
{
    $request = new ShiftRequest;

    return Validator::make($data, $request->rules(), $request->messages());
}

it('accepts database-standard shift times when end time is later', function () {
    $validator = validateShiftTimes([
        'name' => 'Morning Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:30:00',
        'status' => true,
    ]);

    expect($validator->passes())->toBeTrue();
});

it('accepts an overnight shift when end time is on the next day', function () {
    $validator = validateShiftTimes([
        'name' => 'Night Shift',
        'start_time' => '22:00:00',
        'end_time' => '08:00:00',
        'status' => true,
    ]);

    expect($validator->passes())->toBeTrue();
});

it('requires both shift times', function () {
    $validator = validateShiftTimes([
        'name' => 'Morning Shift',
        'start_time' => '',
        'end_time' => '',
    ]);

    expect($validator->errors()->first('start_time'))
        ->toBe('Start Time is required.')
        ->and($validator->errors()->first('end_time'))
        ->toBe('End Time is required.');
});

it('rejects a zero-duration shift with matching start and end times', function () {
    $validator = validateShiftTimes([
        'name' => 'Invalid Shift',
        'start_time' => '17:30:00',
        'end_time' => '17:30:00',
    ]);

    expect($validator->errors()->first('end_time'))
        ->toBe('End Time must be different from Start Time.');
});
