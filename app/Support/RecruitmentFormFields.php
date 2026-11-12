<?php

namespace App\Support;

use App\Models\RecruitmentFormSetting;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Questions of the recruitment-only block. Shared contact details and legal consents remain separate. */
class RecruitmentFormFields
{
    public const TYPES = ['text', 'textarea', 'checkbox', 'date', 'email', 'tel'];

    public const LEGACY_TYPES = [
        'full_name' => 'text',
        'birth_date' => 'date',
        'residence' => 'text',
        'phone_whatsapp' => 'tel',
        'discord_profile' => 'text',
        'how_heard_us' => 'textarea',
        'accepted_rules' => 'checkbox',
        'is_adult' => 'checkbox',
        'accepts_contributions' => 'checkbox',
        'has_required_game_content' => 'checkbox',
        'tuesday_available' => 'checkbox',
        'friday_available' => 'checkbox',
        'has_previous_experience' => 'checkbox',
        'experience_summary' => 'textarea',
    ];

    public static function defaults(): array
    {
        return [
            self::field('full_name', 'Datos personales', 'Nombre y apellidos (reales)', 'text', true, 'Nombre y apellidos'),
            self::field('birth_date', 'Datos personales', 'Fecha de nacimiento', 'date', true),
            self::field('residence', 'Datos personales', 'Lugar de residencia', 'text', true, 'Ciudad / Provincia'),
            self::field('phone_whatsapp', 'Datos personales', 'Teléfono (contacto WhatsApp)', 'tel', true, '+34 ...'),
            self::field('discord_profile', 'Datos personales', 'Perfil de Discord', 'text', false, 'Usuario de Discord o enlace al perfil'),
            self::field('how_heard_us', 'Cómo nos conociste', 'Cuéntanos brevemente cómo llegaste a Squad ALPHA', 'textarea', true, 'YouTube, Twitch, un amigo, redes sociales, buscador...'),
            self::field('accepted_rules', 'Normativa', 'Confirmo haber leído y me comprometo a cumplir la Normativa del Squad ALPHA.', 'checkbox', true, '', '/normativa', 'Ver normativa'),
            self::field('is_adult', 'Mayoría de edad', 'Certifico que soy mayor de edad.', 'checkbox', true),
            self::field('accepts_contributions', 'Aportaciones económicas', 'Acepto que tendré que realizar aportaciones económicas.', 'checkbox', true),
            self::field('has_required_game_content', 'DLCs y ARMA 3 Original', 'Confirmo tener Arma 3 original, así como sus DLC "APEX" y sus CDLCs "S.O.G. Prairie Fire" y "Spearhead 1944", o estar dispuesto/a a comprarlos en caso de ser reclutado/a por Squad ALPHA.', 'checkbox', true),
            self::field('tuesday_available', 'Disponibilidad martes', 'Tengo disponibilidad para participar los martes (de 20:00 a 22:30h). Si no puedes normalmente, deja esta casilla sin marcar.', 'checkbox', false),
            self::field('friday_available', 'Disponibilidad viernes', 'Tengo disponibilidad para participar los viernes (a partir de las 22:30h). Si no puedes normalmente, deja esta casilla sin marcar.', 'checkbox', false),
            self::field('has_previous_experience', 'Experiencia previa', 'Tengo experiencia previa en simulación militar con Arma 3.', 'checkbox', false),
            self::field('experience_summary', 'Experiencia previa', 'Resumen de experiencia en simulación militar en Arma 3 y Arma Reforger', 'textarea', true, 'Cuéntanos comunidades anteriores, tiempo jugando, roles habituales, experiencia con ACE/TFAR/ACRE, etc. Si no tienes experiencia previa, indícalo.'),
        ];
    }

    private static function field(string $key, string $section, string $label, string $type, bool $required, string $placeholder = '', string $url = '', string $linkText = ''): array
    {
        return compact('key', 'section', 'label', 'type', 'required', 'placeholder', 'url', 'linkText') + ['enabled' => true];
    }

    public static function current(): array
    {
        return self::normalize((array) RecruitmentFormSetting::current()->fields, false);
    }

    /** Only enabled fields appear on the website or get validated. */
    public static function visible(): array
    {
        return array_values(array_filter(self::current(), fn (array $field): bool => $field['enabled']));
    }

    /** Normalizing on save prevents duplicate names, invalid markup and reserved field keys. */
    public static function normalize(array $items, bool $saving = true): array
    {
        if (count($items) > 80) {
            throw new InvalidArgumentException('El formulario puede contener un máximo de 80 preguntas.');
        }
        $seen = [];
        $result = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Hay una pregunta que no es válida.');
            }
            $key = (string) ($item['key'] ?? '');
            if ($key === '') {
                $key = 'q_' . Str::lower(Str::random(20));
            }
            if (! preg_match('/^(?:[a-z][a-z0-9_]{0,50})$/', $key) || isset($seen[$key])) {
                throw new InvalidArgumentException('Hay preguntas con identificadores repetidos o incorrectos.');
            }
            $seen[$key] = true;
            $type = self::LEGACY_TYPES[$key] ?? (string) ($item['type'] ?? 'text');
            if (! in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException('Hay preguntas con un tipo no válido.');
            }
            $label = trim((string) ($item['label'] ?? ''));
            $section = trim((string) ($item['section'] ?? ''));
            if ($label === '' || mb_strlen($label) > 500 || mb_strlen($section) > 100) {
                throw new InvalidArgumentException('Cada pregunta necesita un texto (máximo 500 caracteres) y una sección de hasta 100 caracteres.');
            }
            $placeholder = mb_substr(trim((string) ($item['placeholder'] ?? '')), 0, 400);
            $url = trim((string) ($item['url'] ?? ''));
            $url = in_array($url, ['/normativa', '/politica-de-privacidad'], true) ? $url : '';
            $result[] = [
                'key' => $key,
                'type' => $type,
                'section' => $section,
                'label' => $label,
                'placeholder' => $placeholder,
                'required' => (bool) ($item['required'] ?? false),
                'enabled' => (bool) ($item['enabled'] ?? true),
                'url' => $url,
                'linkText' => $url !== '' ? (trim((string) ($item['linkText'] ?? 'Ver enlace')) ?: 'Ver enlace') : '',
            ];
        }

        return $result;
    }

    public static function validationRules(array $fields): array
    {
        $rules = ['recruitment_answers' => ['nullable', 'array']];
        foreach ($fields as $field) {
            $key = 'recruitment_answers.' . $field['key'];
            if ($field['type'] === 'checkbox') {
                $rules[$key] = $field['required'] ? ['accepted'] : ['nullable', 'boolean'];
            } elseif ($field['type'] === 'date') {
                $rules[$key] = [$field['required'] ? 'required' : 'nullable', 'date_format:Y-m-d'];
                if ($field['key'] === 'birth_date') {
                    $rules[$key][] = 'after_or_equal:1900-01-01';
                    $rules[$key][] = 'before_or_equal:' . now()->subYears(18)->toDateString();
                }
            } else {
                $limit = $field['type'] === 'textarea' ? 4000 : (in_array($field['key'], ['full_name', 'residence', 'discord_profile'], true) ? 160 : ($field['key'] === 'phone_whatsapp' ? 40 : 255));
                if ($field['key'] === 'how_heard_us') $limit = 1500;
                $typeRule = match ($field['type']) { 'email' => 'email:rfc', default => 'string' };
                $rules[$key] = [$field['required'] ? 'required' : 'nullable', $typeRule, 'max:' . $limit];
                if ($field['key'] === 'phone_whatsapp') {
                    $rules[$key][] = 'regex:/^[0-9+() .\\-]{7,40}$/';
                }
            }
        }

        return $rules;
    }

    /** Snapshots are stored on each application; later form edits cannot change earlier answers. */
    public static function snapshot(array $fields, array $validated): array
    {
        $values = (array) ($validated['recruitment_answers'] ?? []);
        $answers = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $field['type'] === 'checkbox' ? filter_var($values[$key] ?? false, FILTER_VALIDATE_BOOLEAN) : trim((string) ($values[$key] ?? ''));
            $answers[] = [
                'key' => $key,
                'section' => $field['section'],
                'label' => $field['label'],
                'type' => $field['type'],
                'value' => $value,
            ];
        }

        return $answers;
    }
}
