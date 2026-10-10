<?php

namespace App\Modules\Reconciliation\Http\Requests;

use App\Modules\Reconciliation\Enums\ReconciliationLayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunReconciliationRequest extends FormRequest
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
            'layer' => ['required', Rule::enum(ReconciliationLayer::class)],
            'currency' => ['nullable', 'string', 'size:3'],
            'provider' => ['nullable', 'string', 'max:32'],
            'statement_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scope(): array
    {
        $validated = $this->validated();

        return array_filter([
            'currency' => $validated['currency'] ?? null,
            'provider' => $validated['provider'] ?? null,
            'statement_date' => $validated['statement_date'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
