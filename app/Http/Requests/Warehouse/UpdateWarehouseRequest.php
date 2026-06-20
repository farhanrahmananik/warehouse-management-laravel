<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateWarehouseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('permission', 'warehouses.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', $this->uniqueCodeRule()],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
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
            'code' => 'warehouse code',
            'name' => 'warehouse name',
            'contact_person' => 'contact person',
            'phone' => 'phone number',
            'email' => 'email address',
            'address' => 'address',
            'city' => 'city',
            'is_active' => 'active status',
        ];
    }

    private function uniqueCodeRule(): Unique
    {
        $rule = Rule::unique('warehouses', 'code');
        $warehouse = $this->route('warehouse');

        if ($warehouse instanceof Model) {
            return $rule->ignore($warehouse);
        }

        if (is_numeric($warehouse)) {
            return $rule->ignore((int) $warehouse);
        }

        return $rule;
    }
}
