<?php

namespace App\Modules\Onboarding\Http\Requests;

use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompleteCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::tenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'document' => ['required', 'string', new CpfOrCnpj],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('tenants', 'email')->ignore($tenantId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'domain' => [
                'nullable', 'string', 'max:255',
                'regex:/^(?=.{1,253}$)((?!-)[a-z0-9-]{1,63}(?<!-)\.)+[a-z]{2,63}$/',
                Rule::unique('tenants', 'domain')->ignore($tenantId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->has('document')) {
            $input['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('document'));
        }

        if ($this->filled('domain')) {
            $input['domain'] = Str::lower(trim((string) $this->input('domain')));
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
