<?php

namespace App\Modules\Withdrawals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('withdrawals.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'wallet_id' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'fee' => ['nullable', 'integer', 'min:0'],
            'provider' => ['required', 'string', 'in:fake'],
            'destination' => ['nullable', 'array'],
        ];
    }
}
