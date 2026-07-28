<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SMSTemplate extends Model
{
    protected $table = 'sms_templates';

    protected $fillable = [
        'code',
        'title',
        'type',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SMSLog::class);
    }
}
