<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SMSTemplate extends Model
{
    protected $table = 'sms_templates';
    
    protected $fillable = [
        'title',
        'type',
        'message',
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