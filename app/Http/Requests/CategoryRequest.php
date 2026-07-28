<?php

namespace App\Http\Requests;

use App\Models\Category;
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
        $category = $this->route('category');
        $categoryId = $category instanceof Category ? $category->getKey() : $category;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($categoryId)],
            'code' => ['required', 'string', 'max:32', Rule::unique('categories', 'code')->ignore($categoryId)],
            'inventory_class' => ['sometimes', Rule::in(['fuel', 'lubricant', 'merchandise', 'service'])],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
