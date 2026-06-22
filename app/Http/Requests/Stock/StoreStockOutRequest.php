<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockOutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('permission', 'stock-out.create') ?? false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $this->merge([
            'items' => array_values(array_filter($items, function ($item): bool {
                if (! is_array($item)) {
                    return false;
                }

                foreach (['product_id', 'quantity', 'remarks'] as $field) {
                    if (trim((string) ($item[$field] ?? '')) !== '') {
                        return true;
                    }
                }

                return false;
            })),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'stock_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
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
            'stock_date' => 'stock date',
            'remarks' => 'remarks',
            'items' => 'stock out items',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.remarks' => 'item remarks',
        ];
    }
}
