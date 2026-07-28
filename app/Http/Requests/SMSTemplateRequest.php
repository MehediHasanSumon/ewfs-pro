<?php

namespace App\Http\Requests;

use App\Models\SMSTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SMSTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $template = $this->route('smsTemplate');
        $templateId = $template instanceof SMSTemplate ? $template->getKey() : $template;

        return [
            'code' => ['nullable', 'string', 'max:64', Rule::unique('sms_templates', 'code')->ignore($templateId)],
            'title' => [
                'required',
                'string',
                'max:150',
                Rule::unique('sms_templates', 'title')
                    ->where(fn ($query) => $query->where('type', $this->string('type')->toString()))
                    ->ignore($templateId),
            ],
            'type' => ['required', 'string', 'max:64'],
            'message' => ['required', 'string'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
