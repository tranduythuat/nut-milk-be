<?php

namespace App\Http\Requests;

use App\Services\UnitConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRawMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $units = app(UnitConversionService::class);

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:raw_materials,code'],

            // BẮT BUỘC là base unit (g/ml/cai) — đây là đơn vị lưu trữ
            // vĩnh viễn của nguyên liệu, không đổi được sau khi tạo.
            'unit' => ['required', 'string', Rule::in($units->baseUnits())],

            // Cho phép nhập stock ban đầu theo đơn vị TIỆN LỢI (vd kg),
            // hệ thống tự quy đổi về base unit ở trên trước khi lưu.
            'stock' => ['nullable', 'numeric', 'min:0'],
            'stock_unit' => ['nullable', 'string', Rule::in($units->allUnits())],

            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
