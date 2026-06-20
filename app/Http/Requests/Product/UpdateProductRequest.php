<?php

namespace App\Http\Requests\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('permission', 'products.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:220', $this->uniqueProductRule('slug')],
            'sku' => ['required', 'string', 'max:100', $this->uniqueProductRule('sku')],
            'barcode' => ['nullable', 'string', 'max:150', $this->uniqueProductRule('barcode')],
            'description' => ['nullable', 'string'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'unit_id' => 'unit',
            'name' => 'product name',
            'slug' => 'product slug',
            'sku' => 'SKU',
            'barcode' => 'barcode',
            'description' => 'product description',
            'purchase_price' => 'purchase price',
            'selling_price' => 'selling price',
            'reorder_level' => 'reorder level',
            'is_active' => 'active status',
        ];
    }

    private function uniqueProductRule(string $column): Unique
    {
        $rule = Rule::unique('products', $column);
        $product = $this->route('product');

        if ($product instanceof Model) {
            return $rule->ignore($product);
        }

        if (is_numeric($product)) {
            return $rule->ignore((int) $product);
        }

        if (is_string($product) && $product !== '') {
            return $rule->ignore($product, 'slug');
        }

        return $rule;
    }
}
