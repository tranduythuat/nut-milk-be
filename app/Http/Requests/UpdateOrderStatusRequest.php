<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
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
            /*
             * Chặn SỚM ở tầng request: chỉ 'confirmed' và 'cancelled'
             * là target hợp lệ cho thao tác thủ công qua endpoint này
             * (khớp đúng với bảng transitions trong OrderStatusService).
             *
             * 'pending' không có trong danh sách vì đó là trạng thái
             * khởi tạo (do checkout tạo ra), không phải đích chuyển tới.
             *
             * 'completed' CỐ Ý KHÔNG có trong danh sách: nó chỉ được
             * set tự động bởi OrderCompletionService khi mọi Delivery
             * của đơn đã DELIVERED, không phải qua endpoint này.
             *
             * Việc chặn ở đây giúp trả lỗi 422 rõ ràng ngay ở tầng
             * validation, không cần tốn 1 lượt lockForUpdate() trong
             * OrderStatusService chỉ để phát hiện request không hợp lệ.
             * OrderStatusService::canTransition() vẫn là nguồn chân lý
             * cuối cùng (double-check trạng thái HIỆN TẠI của đơn, điều
             * mà validation ở đây không biết được).
             */
            'status' => [
                'required',
                Rule::in(['confirmed', 'cancelled']),
            ],

            'note' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}
