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
                'max:' . (int) config('checkout.max_quantity_per_item', 50),
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

    public function messages(): array
    {
        $max = (int) config('checkout.max_quantity_per_item', 50);

        return [
            'quantity.max' => "Mỗi sản phẩm chỉ được đặt tối đa {$max} cho một lần. Liên hệ cửa hàng nếu bạn cần số lượng lớn hơn.",
        ];
    }
}
