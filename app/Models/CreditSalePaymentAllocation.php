<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditSalePaymentAllocation extends Model
{
    protected $fillable = ['voucher_id', 'credit_sale_customer_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function customerAllocation(): BelongsTo
    {
        return $this->belongsTo(CreditSaleCustomer::class, 'credit_sale_customer_id');
    }
}
