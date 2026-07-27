<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SMSSetting extends Model
{
    protected $table = 'sms_settings';
    
    protected $fillable = [
        'url',
        'api_key',
        'sender_id',
        'status'
    ];
    
    protected $casts = [
        'status' => 'boolean'
    ];
    
    public function smsLogs()
    {
        return $this->hasMany(SMSLog::class);
    }
}
