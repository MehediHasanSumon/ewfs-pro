<?php

namespace App\Http\Requests;

use App\Helpers\ErpHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($this->route('category')),
            ],
            'status' => ['sometimes', 'boolean'],
        ];

        if ($this->isMethod('post')) {
            $rules['code'] = [
                'required',
                'string',
                'max:32',
                Rule::in(ErpHelper::getReservedCategoryCodes()),
                Rule::unique('categories', 'code'),
            ];
            $rules['inventory_class'] = [
                'sometimes',
                Rule::in(['fuel', 'lubricant', 'merchandise', 'service']),
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post') && $this->has('code')) {
            $this->merge([
                'code' => (string) $this->input('code'),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'code.in' => 'The selected category code is not a reserved ERP category code.',
        ];
    }
}
