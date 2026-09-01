<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
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
            'product_variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
            'combo_id' => [
                'nullable',
                'integer',
                'exists:combos,id',
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $productVariantId = $this->input('product_variant_id');
            $comboId = $this->input('combo_id');
            if (!$productVariantId && !$comboId) {
                $validator->errors()->add('item', 'Vui lòng chọn sản phẩm hoặc combo.');
            }
            if ($productVariantId && $comboId) {
                $validator->errors()->add('item', 'Chỉ được chọn sản phẩm hoặc combo, không được chọn cả hai.');
            }
        },];
    }
}
