<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SMSLog extends Model
{
    protected $table = 'sms_logs';
    
    protected $fillable = [
        'phone_number',
        'message',
        'sms_template_id',
        'sms_setting_id',
        'status',
        'response',
        'sent_at',
        'error_message'
    ];
    
    protected $casts = [
        'response' => 'array',
        'sent_at' => 'datetime'
    ];
    
    public function smsTemplate()
    {
        return $this->belongsTo(SMSTemplate::class);
    }
    
    public function smsSetting()
    {
        return $this->belongsTo(SMSSetting::class);
    }
}