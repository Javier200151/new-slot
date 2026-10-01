<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmación | Squad ALPHA</title>
</head>
<body style="margin:0;background:#05070a;color:#e5e7eb;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#05070a;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:720px;background:#0b0f14;border:1px solid #252b35;border-radius:14px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 36px;background:#080b10;border-bottom:1px solid #252b35;">
                        <div style="color:#f59e0b;font-size:12px;font-weight:800;letter-spacing:2px;text-transform:uppercase;">Squad ALPHA</div>
                        <h1 style="margin:8px 0 8px;color:#fff;font-size:24px;line-height:1.25;">
                            {{ $submission->is_recruitment ? 'Solicitud de alistamiento recibida' : 'Consulta recibida' }}
                        </h1>
                        <p style="margin:0;color:#adb4c0;font-size:14px;line-height:1.7;">
                            Hola {{ $submission->nickname }}, hemos recibido correctamente tu {{ $submission->is_recruitment ? 'solicitud de alistamiento' : 'consulta' }}. Esta es una copia de la información enviada.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:26px 36px 12px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                            @foreach([
                                'Nick' => $submission->nickname,
                                'Email' => $submission->email,
                                'Nombre y apellidos' => $submission->is_recruitment ? $submission->full_name : null,
                                'Fecha de nacimiento' => $submission->is_recruitment ? $submission->birth_date?->format('d/m/Y') : null,
                                'Residencia' => $submission->is_recruitment ? $submission->residence : null,
                                'WhatsApp' => $submission->is_recruitment ? $submission->phone_whatsapp : null,
                                'Discord' => $submission->is_recruitment ? $submission->discord_profile : null,
                            ] as $label => $value)
                                @if(filled($value))
                                    <tr>
                                        <td style="width:180px;padding:10px 12px;color:#7f8998;font-size:12px;font-weight:700;text-transform:uppercase;">{{ $label }}</td>
                                        <td style="padding:10px 12px;color:#f7f7f8;font-size:14px;">{{ $value }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:12px 36px;">
                        <div style="margin-bottom:8px;color:#f59e0b;font-size:11px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">Mensaje</div>
                        <div style="padding:16px;background:#090c11;border:1px solid #252b35;border-radius:10px;color:#d8dde5;font-size:14px;line-height:1.7;">
                            {!! nl2br(e($submission->message)) !!}
                        </div>
                    </td>
                </tr>

                @if($submission->is_recruitment)
                    <tr>
                        <td style="padding:12px 36px;">
                            <div style="margin-bottom:8px;color:#f59e0b;font-size:11px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">Cómo conociste Squad ALPHA</div>
                            <div style="padding:16px;background:#090c11;border:1px solid #252b35;border-radius:10px;color:#d8dde5;font-size:14px;line-height:1.7;">
                                {!! nl2br(e($submission->how_heard_us)) !!}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 36px;">
                            <div style="margin-bottom:8px;color:#f59e0b;font-size:11px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">Experiencia</div>
                            <div style="padding:16px;background:#090c11;border:1px solid #252b35;border-radius:10px;color:#d8dde5;font-size:14px;line-height:1.7;">
                                {!! nl2br(e($submission->experience_summary)) !!}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 36px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;background:#090c11;border:1px solid #252b35;border-radius:10px;overflow:hidden;">
                                @foreach([
                                    'Normativa aceptada' => $submission->accepted_rules,
                                    'Mayor de edad' => $submission->is_adult,
                                    'Acepta aportaciones económicas' => $submission->accepts_contributions,
                                    'Arma 3 + DLC/CDLC requeridos' => $submission->has_required_game_content,
                                    'Disponible los martes' => $submission->tuesday_available,
                                    'Disponible los viernes' => $submission->friday_available,
                                    'Experiencia previa' => $submission->has_previous_experience,
                                    'Política de privacidad aceptada' => $submission->accepted_privacy,
                                    'Consentimiento de contacto' => $submission->accepted_contact,
                                ] as $label => $value)
                                    <tr>
                                        <td style="padding:11px 14px;border-bottom:1px solid #202630;color:#adb4c0;font-size:13px;">{{ $label }}</td>
                                        <td align="right" style="padding:11px 14px;border-bottom:1px solid #202630;color:{{ $value ? '#86efac' : '#fca5a5' }};font-size:12px;font-weight:800;">{{ $value ? 'SÍ' : 'NO' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:22px 36px 30px;border-top:1px solid #252b35;color:#7f8998;font-size:12px;line-height:1.7;text-align:center;">
                        No necesitas volver a enviar el formulario. El equipo de Squad ALPHA lo revisará desde NewSlot.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
