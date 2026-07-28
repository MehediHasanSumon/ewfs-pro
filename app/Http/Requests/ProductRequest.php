<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->getKey() : $product;

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'product_code' => ['nullable', 'string', 'max:64', Rule::unique('products', 'product_code')->ignore($productId)],
            'product_name' => ['required', 'string', 'max:150'],
            'product_slug' => ['nullable', 'string', 'max:180', Rule::unique('products', 'product_slug')->ignore($productId)],
            'country_Of_origin' => ['nullable', 'string', 'max:100'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'is_inventory_item' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    public function attributesForPersistence(): array
    {
        $attributes = $this->validated();
        $attributes['country_of_origin'] = $attributes['country_Of_origin']
            ?? $attributes['country_of_origin']
            ?? null;

        unset($attributes['country_Of_origin']);

        return $attributes;
    }
}
