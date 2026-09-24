<?php

namespace App\Http\Requests;

use App\Services\UnitConversionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncProductVariantIngredientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $units = app(UnitConversionService::class);

        return [
            'ingredients' => ['present', 'array'],
            'ingredients.*.raw_material_id' => ['required', 'integer', 'distinct', 'exists:raw_materials,id'],
            'ingredients.*.quantity' => ['required', 'numeric', 'min:0.0001'],

            // Đơn vị người khai BOM dùng (vd bếp trưởng nghĩ theo "lít").
            // null = mặc định base unit của raw material đó.
            'ingredients.*.unit' => ['nullable', 'string', Rule::in($units->allUnits())],
        ];
    }
}
