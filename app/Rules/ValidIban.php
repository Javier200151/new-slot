<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $iban = strtoupper(preg_replace('/\s+/', '', trim((string) $value)));

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            $fail('Introduce un IBAN válido, con código de país y dígitos de control.');
            return;
        }

        // Comprobar dígitos de control ISO 13616 con aritmética modular,
        // sin convertir el IBAN en un entero demasiado grande para PHP.
        $reordered = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($reordered) as $char) {
            $digits = ctype_alpha($char) ? (string) (ord($char) - ord('A') + 10) : $char;
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }
        if ($remainder !== 1) {
            $fail('Los dígitos de control del IBAN no son correctos.');
        }
    }
}
