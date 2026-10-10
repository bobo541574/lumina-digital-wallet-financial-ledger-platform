<?php

namespace App\Modules\Reconciliation\Http\Requests;

use App\Modules\Reconciliation\Enums\ReconciliationItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveReconciliationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('reconciliation.run');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                ReconciliationItemStatus::Resolved->value,
                ReconciliationItemStatus::Ignored->value,
            ])],
            'note' => ['nullable', 'string', 'max:2000'],
            'resolution' => ['nullable', 'array'],
        ];
    }
}
