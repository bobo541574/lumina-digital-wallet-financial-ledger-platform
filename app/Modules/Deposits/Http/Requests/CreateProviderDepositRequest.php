<?php

namespace App\Modules\Deposits\Http\Requests;

use App\Modules\PaymentProviders\Enums\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProviderDepositRequest extends FormRequest
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
            'provider' => ['required', 'string', Rule::in(PaymentProvider::collectable())],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
