<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
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
            'customer_name' => [
                'required',
                'string',
                'max:255',
            ],

            'customer_phone' => [
                'required',
                'string',
                'max:20',
            ],

            'customer_address' => [
                'required',
                'string',
                'max:500',
            ],

            'customer_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'payment_method' => [
                'required',
                'string',
                'in:cod,bank_transfer',
            ],

            'delivery_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
            ],

            'delivery_slot_id' => [
                'required',
                'integer',
                'exists:delivery_slots,id',
            ],
        ];
    }
}
