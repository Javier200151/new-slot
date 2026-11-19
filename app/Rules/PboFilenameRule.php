<?php

namespace App\Rules;

use App\Support\ActivityPbo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PboFilenameRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof TemporaryUploadedFile && ! ActivityPbo::validFilename($value->getClientOriginalName())) {
            $fail('El nombre del PBO no es válido. Usa un nombre sin espacios ni tildes y terminado en .pbo.');
        }
    }
}
