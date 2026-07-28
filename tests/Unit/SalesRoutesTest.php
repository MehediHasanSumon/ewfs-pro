<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

it('exposes POS sales routes without the removed batch sale endpoint', function () {
    expect(Route::has('sales.index'))->toBeTrue()
        ->and(Route::has('sales.customer-lookup'))->toBeTrue()
        ->and(Route::has('sales.invoice.pdf'))->toBeTrue()
        ->and(Route::has('sales.batch.pdf'))->toBeFalse();
});
