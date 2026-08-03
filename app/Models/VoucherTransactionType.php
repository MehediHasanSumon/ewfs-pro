<?php

namespace App\Models;

use App\Helpers\VoucherTransactionTypeHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class VoucherTransactionType extends Model
{
    protected $fillable = [
        'voucher_category_id',
        'code',
        'name',
        'voucher_type',
        'report_bucket_code',
        'description',
        'sort_order',
        'status',
        'is_system',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'status' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (VoucherTransactionType $transactionType): void {
            foreach (['voucher_category_id', 'code', 'voucher_type', 'is_system'] as $attribute) {
                if ($transactionType->isDirty($attribute)) {
                    $transactionType->{$attribute} = $transactionType->getOriginal($attribute);
                }
            }
        });

        static::deleting(function (VoucherTransactionType $transactionType): void {
            if ($transactionType->isSystemType()) {
                throw ValidationException::withMessages([
                    'voucher_transaction_type' => 'System voucher transaction types cannot be deleted.',
                ]);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeForVoucherType(Builder $query, string $voucherType): Builder
    {
        return $query->where('voucher_type', $voucherType);
    }

    public function scopeForCategory(
        Builder $query,
        int $voucherCategoryId
    ): Builder {
        return $query->where('voucher_category_id', $voucherCategoryId);
    }

    public function getTypeAttribute(): ?string
    {
        return $this->voucher_type;
    }

    public function setTypeAttribute(?string $value): void
    {
        $this->attributes['voucher_type'] = $value;
    }

    public function isSystemType(): bool
    {
        if ($this->is_system) {
            return true;
        }

        $categoryCode = $this->relationLoaded('voucherCategory')
            ? $this->voucherCategory?->code
            : $this->voucherCategory()->value('code');

        return $categoryCode !== null
            && VoucherTransactionTypeHelper::isSystemIdentity($categoryCode, $this->code);
    }

    public function voucherCategory(): BelongsTo
    {
        return $this->belongsTo(VoucherCategory::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
