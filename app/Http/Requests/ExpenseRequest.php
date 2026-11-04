<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
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
     * @return array{description: list<string>, category: list<string>, amount: list<string>, expense_date: list<string>}
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
