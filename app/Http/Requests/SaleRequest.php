<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'uuid'],
            'sale_type' => ['required', 'in:walk_in,delivery'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'delivery_address' => ['exclude_unless:sale_type,delivery', 'required', 'string', 'max:10000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'initial_payment_type' => ['sometimes', 'in:none,full,partial'],
            'initial_payment_amount' => ['exclude_unless:initial_payment_type,partial', 'required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'payment_method' => ['exclude_unless:initial_payment_type,full,partial', 'required', 'in:cash,gcash'],
            'track_gallon_exchange' => ['sometimes', 'boolean'],
            'filled_gallons_out' => ['exclude_unless:track_gallon_exchange,true', 'required', 'integer', 'min:0', 'max:4294967295'],
            'empty_gallons_returned' => ['exclude_unless:track_gallon_exchange,true', 'required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
