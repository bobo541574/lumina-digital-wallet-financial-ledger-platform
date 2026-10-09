<?php

namespace App\Modules\Deposits\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('deposits.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'wallet_id' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
