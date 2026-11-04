<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GallonExchangeRequest extends FormRequest
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
     * @return array{filled_gallons_out: list<string>, empty_gallons_returned: list<string>}
     */
    public function rules(): array
    {
        return [
            'filled_gallons_out' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'empty_gallons_returned' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
