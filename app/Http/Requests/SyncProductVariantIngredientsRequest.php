<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncProductVariantIngredientsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ingredients' => ['present', 'array'],
            'ingredients.*.raw_material_id' => ['required', 'integer', 'distinct', 'exists:raw_materials,id'],
            'ingredients.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ];
    }
}
