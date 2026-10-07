<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxDeliveries = (int) config('checkout.max_deliveries_per_order', 3);

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_address' => ['required', 'string', 'max:500'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'string', 'in:cod,bank_transfer'],

            /*
             * 'max' ở đây chặn SỐ LƯỢNG ĐỢT GIAO — không phải số sản
             * phẩm. Message mặc định của Laravel cho rule max trên
             * mảng ("The deliveries field must not have more than N
             * items.") không đủ rõ cho khách hàng, nên override bằng
             * messages() bên dưới.
             */
            'deliveries' => ['required', 'array', 'min:1', "max:{$maxDeliveries}"],

            'deliveries.*.delivery_date' => ['required', 'date', 'date_format:Y-m-d'],
            'deliveries.*.delivery_slot_id' => ['required', 'integer', 'exists:delivery_slots,id'],
            'deliveries.*.note' => ['nullable', 'string', 'max:500'],

            'deliveries.*.items' => ['required', 'array', 'min:1'],
            'deliveries.*.items.*.cart_item_id' => ['required', 'integer'],
            'deliveries.*.items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        $maxDeliveries = (int) config('checkout.max_deliveries_per_order', 3);

        return [
            'deliveries.max' => "Đơn hàng chỉ được chia tối đa {$maxDeliveries} đợt giao.",
        ];
    }

    /**
     * Validate bổ sung không thể biểu diễn bằng rule string đơn giản:
     * chặn trường hợp có đợt giao trùng NGÀY + KHUNG GIỜ với đợt khác
     * trong cùng request — hai đợt giao hệt nhau về thời điểm là vô
     * nghĩa (nên gộp lại thành 1 đợt), dễ gây nhầm lẫn khi vận hành
     * xử lý (2 Delivery riêng biệt cùng xuất hiện trong 1 Production
     * Plan, cùng 1 slot).
     */
    public function after(): array
    {
        return [function ($validator) {
            $deliveries = $this->input('deliveries', []);

            if (! is_array($deliveries)) {
                return;
            }

            $seen = [];

            foreach ($deliveries as $index => $delivery) {
                $date = $delivery['delivery_date'] ?? null;
                $slotId = $delivery['delivery_slot_id'] ?? null;

                if (! $date || ! $slotId) {
                    continue;
                }

                $key = "{$date}|{$slotId}";

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "deliveries.{$index}",
                        'Hai đợt giao không được trùng cả ngày và khung giờ — hãy gộp chung thành 1 đợt.'
                    );
                    continue;
                }

                $seen[$key] = true;
            }
        }];
    }
}
