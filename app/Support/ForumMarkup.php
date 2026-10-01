<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Alias de compatibilidad. El parser canónico de NewSlot es BbcodeMarkup.
 */
class ForumMarkup
{
    public static function render(string|array|null $value): HtmlString
    {
        return BbcodeMarkup::render($value);
    }
}
