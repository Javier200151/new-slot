<?php

namespace App\Filament\Forms;

use App\Support\BbcodeMarkup;
use Filament\Forms\Components\Textarea;

final class BbcodeTextarea
{
    public static function make(string $name): Textarea
    {
        return Textarea::make($name)
            ->rows(6)
            ->formatStateUsing(
                fn (mixed $state): string => BbcodeMarkup::toEditor(
                    is_array($state) ? $state : (string) ($state ?? '')
                )
            )
            ->extraInputAttributes([
                'data-newslot-bbcode' => '1',
            ])
            ->helperText('BBCode disponible: formato, listas, citas, enlaces, imágenes/GIF por URL y alineación.');
    }
}
