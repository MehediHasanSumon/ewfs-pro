<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePaymentAllocation extends Model
{
    protected $fillable = ['voucher_id', 'sale_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
