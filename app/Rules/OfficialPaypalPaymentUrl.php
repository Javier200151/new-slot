<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OfficialPaypalPaymentUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $url = trim((string) $value);
        $parts = parse_url($url);
        $host = strtolower((string) (is_array($parts) ? ($parts['host'] ?? '') : ''));
        $scheme = strtolower((string) (is_array($parts) ? ($parts['scheme'] ?? '') : ''));

        if (! filter_var($url, FILTER_VALIDATE_URL)
            || $scheme !== 'https'
            || ! in_array($host, ['paypal.com', 'www.paypal.com', 'paypal.me', 'www.paypal.me'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            $fail('Utiliza un enlace HTTPS oficial de paypal.com o paypal.me.');
        }
    }
}
