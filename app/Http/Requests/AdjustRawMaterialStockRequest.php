<?php

namespace App\Http\Requests;

use App\Services\UnitConversionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustRawMaterialStockRequest extends FormRequest
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
            'stock' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', Rule::in($units->allUnits())],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
