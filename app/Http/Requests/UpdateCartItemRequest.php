<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1', 'max:' . (int) config('checkout.max_quantity_per_item', 50),],
        ];
    }

    public function messages(): array
    {
        $max = (int) config('checkout.max_quantity_per_item', 50);

        return [
            'quantity.max' => "Mỗi sản phẩm chỉ được đặt tối đa {$max} cho một lần. Liên hệ cửa hàng nếu bạn cần số lượng lớn hơn.",
        ];
    }
}
