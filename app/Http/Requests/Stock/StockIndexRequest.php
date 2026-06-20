<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StockIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('permission', 'stock.view') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
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
            'warehouse_id' => 'warehouse',
            'product_id' => 'product',
        ];
    }
}
