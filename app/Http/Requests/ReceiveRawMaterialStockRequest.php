<?php

namespace App\Http\Requests;

use App\Services\UnitConversionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveRawMaterialStockRequest extends FormRequest
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
            'quantity' => ['required', 'numeric', 'min:0.0001'],

            // Đơn vị người nhập dùng. null = mặc định base unit của
            // material (không quy đổi gì). Phải CÙNG type với base unit
            // của material đó — kiểm tra ở tầng service khi convert().
            'unit' => ['nullable', 'string', Rule::in($units->allUnits())],

            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
