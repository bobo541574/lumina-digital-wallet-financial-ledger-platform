<?php

namespace App\Modules\Wallets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
