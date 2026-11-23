<?php

namespace App\Services\MissionControl;

/** Plain-text templates used by the production notifier and the Filament preview. */
final class MissionControlTelegramTemplates
{
    /** @return array<string,string> */
    public static function labels(): array
    {
        return [
            'files_ready' => 'PBO y addons preparados',
            'files_missing' => 'Falta el PBO o algún addon',
            'files_unknown' => 'No se pueden verificar PBO y addons',
            'launch_ready' => 'Operativo arrancado correctamente',
            'launch_manual' => 'Operativo ya arrancado manualmente',
            'launch_pending' => 'Arranque pendiente o en curso',
            'launch_failed' => 'Fallo de arranque',
            'agent_notice' => 'Aviso directo de Mission Control',
            'test' => 'Mensaje de prueba',
        ];
    }

    /** @return array<string,string> */
    public static function defaults(): array
    {
        $files = "SERVER [=ALPHA=] · Comprobación T−{antelacion} min\n{estado}\n\nEvento: {evento}\nInicio: {fecha}\n\n{detalles}\n\nArranque automático: {automatico}\n{proxima}\n{url}";
        $launch = "SERVER [=ALPHA=] · Comprobación de arranque\nEvento: {evento}\nInicio: {fecha}\n\n{resultado}\n{url}";
        return [
            'files_ready' => $files,
            'files_missing' => $files,
            'files_unknown' => $files,
            'launch_ready' => $launch,
            'launch_manual' => $launch,
            'launch_pending' => $launch,
            'launch_failed' => $launch,
            'agent_notice' => "SERVER [=ALPHA=] · Aviso de Mission Control\nTipo: {tipo}\n\n{aviso}",
            'test' => "SERVER [=ALPHA=] · Mensaje de prueba\nLa conexión con Telegram funciona. No se ha ejecutado ninguna orden sobre los servidores.",
        ];
    }

    /** @return array<string,string> */
    public static function samples(): array
    {
        return [
            'evento' => 'Pacific Shield',
            'fecha' => '27/11/2026 22:00',
            'antelacion' => '120',
            'estado' => 'Archivos preparados',
            'detalles' => "[OK] PBO: Pacific_Shield.Tanoa.pbo\n[OK] Addons: 25 instalados",
            'automatico' => 'activado (15 min antes)',
            'proxima' => 'Los archivos están preparados; no es necesario repetir la comprobación.',
            'url' => 'https://www.squadalpha.es/eventos/123',
            'resultado' => 'El operativo ya estaba arrancado manualmente con el evento correcto.',
            'tipo' => 'levantado',
            'aviso' => 'Servidor levantado: Pacific Shield. Un administrador debe realizar el login.',
        ];
    }

    /** @param array<string,mixed> $values @param array<string,mixed> $overrides */
    public static function render(string $key, array $values = [], array $overrides = []): string
    {
        $defaults = self::defaults();
        if (! array_key_exists($key, $defaults)) {
            throw new \InvalidArgumentException('Tipo de notificación desconocido.');
        }
        $candidate = $overrides[$key] ?? null;
        $template = is_string($candidate) && trim($candidate) !== '' && mb_strlen($candidate) <= 3000
            ? $candidate : $defaults[$key];
        $data = $values;
        $replacements = [];
        foreach ($data as $name => $value) {
            if (is_scalar($value)) {
                $replacements['{'.$name.'}'] = (string) $value;
            }
        }
        $rendered = strtr($template, $replacements);
        // Unknown or context-inapplicable tokens must never inherit demo values.
        $rendered = preg_replace('/\{[a-z_]+\}/u', '—', $rendered) ?? $rendered;
        return mb_substr(trim($rendered), 0, 4000);
    }

    /** @param array<string,mixed> $overrides */
    public static function preview(string $key, array $overrides = []): string
    {
        $samples = self::samples();
        if ($key === 'files_missing') {
            $samples['estado'] = 'Faltan archivos o addons';
            $samples['detalles'] = "[OK] PBO: Pacific_Shield.Tanoa.pbo\n[FALTA] Addons: @RHSAFRF";
            $samples['proxima'] = 'Se repetirá la comprobación en 30 minutos.';
        } elseif ($key === 'files_unknown') {
            $samples['estado'] = 'No se puede verificar la preparación';
            $samples['detalles'] = '[SIN VERIFICAR] No se puede comprobar el PBO ni los addons: Mission Control no responde.';
            $samples['automatico'] = 'sin verificar';
        } elseif ($key === 'launch_ready') {
            $samples['resultado'] = 'El servidor está en marcha con el evento correcto.';
        } elseif ($key === 'launch_pending') {
            $samples['resultado'] = 'Windows sigue arrancando el operativo. Se continuará comprobando.';
        } elseif ($key === 'launch_failed') {
            $samples['resultado'] = 'No se ha podido arrancar el operativo: faltan addons.';
        }
        return self::render($key, $samples, $overrides);
    }

    /** Convert the v97 status symbols to plain text. Source notices are also sanitized. */
    public static function plain(string $text): string
    {
        $text = str_replace(['✅','❌','⚠️','⚠','❓','⏳','🚀','📦','🖥️'], ['[OK]','[FALTA]','[AVISO]','[SIN VERIFICAR]','[PENDIENTE]','','','',''], $text);
        return trim(preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $text) ?? $text);
    }
}
