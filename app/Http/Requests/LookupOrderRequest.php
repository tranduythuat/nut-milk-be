<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
        ];
    }
}
