<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Sync;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MutationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('tenant_membership');
    }

    public function rules(): array
    {
        $max = (int) config('sync.push.max_batch', 100);

        return [
            'mutations' => ['required', 'array', 'min:1', 'max:'.$max],
            'mutations.*.client_uuid' => ['required', 'string', 'min:8', 'max:64'],
            'mutations.*.idempotency_key' => ['required', 'string', 'min:8', 'max:128'],
            'mutations.*.entity' => ['required', 'string', Rule::in(array_keys((array) config('sync.entities', [])))],
            'mutations.*.op' => ['required', Rule::in(['create', 'update', 'delete'])],
            'mutations.*.payload' => ['required', 'array'],
            'mutations.*.base_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
