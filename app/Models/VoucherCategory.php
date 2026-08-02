<?php

namespace App\Models;

use App\Helpers\VoucherCategoryHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class VoucherCategory extends Model
{
    protected $fillable = [
        'code',
        'name',
        'report_bucket_code',
        'description',
        'status',
        'sort_order',
        'is_system',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer',
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (VoucherCategory $voucherCategory): void {
            if ($voucherCategory->isDirty('code')) {
                $voucherCategory->code = $voucherCategory->getOriginal('code');
            }

            if ($voucherCategory->isDirty('is_system')) {
                $voucherCategory->is_system = $voucherCategory->getOriginal('is_system');
            }
        });

        static::deleting(function (VoucherCategory $voucherCategory): void {
            if ($voucherCategory->isSystemCategory()) {
                throw ValidationException::withMessages([
                    'voucher_category' => 'System voucher categories cannot be deleted.',
                ]);
            }
        });
    }

    public function isSystemCategory(): bool
    {
        return $this->is_system
            || VoucherCategoryHelper::isSystemCode($this->code);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function paymentSubTypes(): HasMany
    {
        return $this->voucherTransactionTypes();
    }

    public function voucherTransactionTypes(): HasMany
    {
        return $this->hasMany(VoucherTransactionType::class);
    }
}
