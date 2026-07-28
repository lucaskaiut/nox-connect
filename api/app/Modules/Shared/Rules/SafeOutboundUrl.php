<?php

namespace App\Modules\Shared\Rules;

use App\Modules\Shared\Support\UrlSecurityValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class SafeOutboundUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('O campo :attribute deve ser uma URL válida.');

            return;
        }

        try {
            app(UrlSecurityValidator::class)->assertSafe($value);
        } catch (InvalidArgumentException $e) {
            $fail($e->getMessage());
        }
    }
}
