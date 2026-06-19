<?php

namespace App\Http\Requests\Category;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('permission', 'categories.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', $this->uniqueSlugRule()],
            'description' => ['nullable', 'string'],
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
            'name' => 'category name',
            'slug' => 'category slug',
            'description' => 'category description',
            'is_active' => 'active status',
        ];
    }

    private function uniqueSlugRule(): Unique
    {
        $rule = Rule::unique('categories', 'slug');
        $category = $this->route('category');

        if ($category instanceof Model) {
            return $rule->ignore($category);
        }

        if (is_numeric($category)) {
            return $rule->ignore((int) $category);
        }

        if (is_string($category) && $category !== '') {
            return $rule->ignore($category, 'slug');
        }

        return $rule;
    }
}
