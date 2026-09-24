<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRawMaterialRequest extends FormRequest
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
        $rawMaterialId = $this->route('rawMaterial')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:raw_materials,code,' . $rawMaterialId],

            /*
             * 'unit' CỐ Ý KHÔNG có trong rules này — không cho sửa sau
             * khi tạo. Đổi unit sẽ làm sai lệch toàn bộ stock,
             * reserved_quantity, min_stock đã lưu (chúng đang ở đơn vị
             * cũ nhưng sẽ bị đọc nhầm sang đơn vị mới). Nếu client gửi
             * kèm field 'unit', FormRequest::validated() sẽ tự loại bỏ
             * vì không nằm trong rules -> field bị bỏ qua an toàn.
             */

            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
