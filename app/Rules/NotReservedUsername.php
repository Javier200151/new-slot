<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class NotReservedUsername implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $username = $this->normalize((string) $value);

        $reservedNames = collect(config('reserved-usernames.names', []))
            ->map(fn (string $name): string => $this->normalize($name));

        if ($reservedNames->contains($username)) {
            $fail('Este nombre de usuario está reservado.');
        }
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(Str::lower(trim($value)));

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }
}
