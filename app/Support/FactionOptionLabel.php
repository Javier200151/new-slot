<?php

namespace App\Support;

use App\Models\Faction;
use Illuminate\Support\Facades\Storage;

class FactionOptionLabel
{
    public static function make(Faction $faction): string
    {
        $name = e($faction->name);

        $icons = [];

        if (filled($faction->side?->image)) {
            $imageUrl = e(
                Storage::disk('public')->url(
                    $faction->side->image
                )
            );

            $sideName = e(
                $faction->side?->name
                ?? 'Bando'
            );

            $icons[] = <<<HTML
                <img
                    src="{$imageUrl}"
                    alt=""
                    title="{$sideName}"
                    style="width:24px;height:24px;object-fit:contain;flex:0 0 auto;"
                >
            HTML;
        }

        if (filled($faction->army?->country?->image)) {
            $imageUrl = e(
                Storage::disk('public')->url(
                    $faction->army->country->image
                )
            );

            $countryName = e(
                $faction->army->country?->name
                ?? 'País'
            );

            $icons[] = <<<HTML
                <img
                    src="{$imageUrl}"
                    alt=""
                    title="{$countryName}"
                    style="width:28px;height:22px;object-fit:contain;flex:0 0 auto;"
                >
            HTML;
        }

        $iconsHtml = implode('', $icons);

        return <<<HTML
            <div style="display:flex;align-items:center;gap:8px;min-width:0;">
                {$iconsHtml}
                <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {$name}
                </span>
            </div>
        HTML;
    }
}
