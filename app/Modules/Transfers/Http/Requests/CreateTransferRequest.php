<?php

namespace App\Modules\Transfers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('transfers.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sender_wallet_id' => ['required', 'uuid'],
            'recipient_wallet_id' => ['required', 'uuid'],
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
