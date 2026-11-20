<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Addon;
use Illuminate\Support\Collection;

/** Generates Arma 3 Launcher-compatible presets for the public download and PBO export. */
final class ActivityAddonsHtml
{
    private const HEADER = <<<'HTML'
<?xml version="1.0" encoding="utf-8"?>
<html>
  <!--Created by Arma 3 Launcher: https://arma3.com-->
  <head>
    <meta name="arma:Type" content="list" />
    <meta name="generator" content="Arma 3 Launcher - https://arma3.com" />
    <title>Arma 3</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" type="text/css" />
    <style>
body {
	margin: 0;
	padding: 0;
	color: #fff;
	background: #000;	
}

body, th, td {
	font: 95%/1.3 Roboto, Segoe UI, Tahoma, Arial, Helvetica, sans-serif;
}

td {
    padding: 3px 30px 3px 0;
}

h1 {
    padding: 20px 20px 0 20px;
    color: white;
    font-weight: 200;
    font-family: segoe ui;
    font-size: 3em;
    margin: 0;
}

em {
    font-variant: italic;
    color:silver;
}

.before-list {
    padding: 5px 20px 10px 20px;
}

.mod-list {
    background: #222222;
    padding: 20px;
}

.dlc-list {
    background: #222222;
    padding: 20px;
}

.footer {
    padding: 20px;
    color:gray;
}

.whups {
    color:gray;
}

a {
    color: #D18F21;
    text-decoration: underline;
}

a:hover {
    color:#F1AF41;
    text-decoration: none;
}

.from-steam {
    color: #449EBD;
}
.from-local {
    color: gray;
}

</style>
  </head>
  <body>
    <h1>Arma 3 Mods</h1>
    <p class="before-list">
      <em>To import this preset, drag this file onto the Launcher window. Or click the MODS tab, then PRESET in the top right, then IMPORT at the bottom, and finally select this file.</em>
    </p>
    <div class="mod-list">
      <table>
HTML;

    private const FOOTER = <<<'HTML'

      </table>
    </div>
    <div class="footer">
      <span>Created by Arma 3 Launcher by Bohemia Interactive.</span>
    </div>
  </body>
</html>
HTML;

    public static function forActivity(Activity $activity): string
    {
        return self::fromIds($activity->addons['addon_ids'] ?? []);
    }

    public static function fromIds(array $addonIds): string
    {
        $ids = collect($addonIds)->map(fn ($id): int => (int) $id)->filter()->unique()->all();
        $addons = Addon::query()->whereIn('id', $ids)->orderBy('name')->get(['name', 'download_url']);

        return self::fromAddons($addons);
    }

    /** Retained for older integrations and unit tests. */
    public static function fromNames(array $names): string
    {
        return self::fromAddons(collect($names)->map(fn ($name): array => ['name' => $name, 'download_url' => null]));
    }

    /** @param iterable<object|array<string, mixed>> $addons */
    public static function fromAddons(iterable $addons): string
    {
        $rows = [];
        foreach ($addons as $addon) {
            $name = is_array($addon) ? ($addon['name'] ?? '') : ($addon->name ?? '');
            $url = is_array($addon) ? ($addon['download_url'] ?? null) : ($addon->download_url ?? null);
            $safeName = htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $row = '        <tr data-type="ModContainer">' . "\n"
                . '          <td data-type="DisplayName">' . $safeName . '</td>';

            if ($safeUrl = self::safeUrl($url)) {
                $safeHref = htmlspecialchars($safeUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $host = strtolower((string) parse_url($safeUrl, PHP_URL_HOST));
                $isSteam = $host === 'steamcommunity.com' || str_ends_with($host, '.steamcommunity.com');
                $className = $isSteam ? 'from-steam' : 'from-local';
                $provider = $isSteam ? 'Steam' : 'Web';
                $row .= "\n" . '          <td>'
                    . "\n" . '            <span class="' . $className . '">' . $provider . '</span>'
                    . "\n" . '          </td>'
                    . "\n" . '          <td>'
                    . "\n" . '            <a href="' . $safeHref . '" data-type="Link">' . $safeHref . '</a>'
                    . "\n" . '          </td>';
            }

            $rows[] = $row . "\n" . '        </tr>';
        }

        return self::HEADER . "\n" . implode("\n", $rows) . self::FOOTER;
    }

    public static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['https', 'http'], true) && is_string($host) && $host !== ''
            ? $url
            : null;
    }

    public static function htmlFilename(string $pboFilename): string
    {
        return substr($pboFilename, 0, -4) . '.html';
    }
}
