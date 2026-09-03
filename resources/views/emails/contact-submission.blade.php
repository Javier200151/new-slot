<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $submission->is_recruitment ? 'Solicitud de alistamiento' : 'Consulta de contacto' }} | Squad ALPHA</title>
</head>
<body style="margin:0;padding:0;background-color:#05070a;font-family:Arial,Helvetica,sans-serif;color:#f7f7f8;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $submission->is_recruitment ? 'Nueva solicitud de alistamiento' : 'Nueva consulta web' }} de {{ $submission->nickname }}.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#05070a;">
        <tr>
            <td align="center" style="padding:48px 20px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;">
                    <tr>
                        <td align="center" style="padding-bottom:28px;">
                            <img src="{{ url('/images/sqa-header-logo.png') }}" alt="Squad ALPHA" width="180" style="display:block;width:180px;max-width:100%;height:auto;border:0;">
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#0e1219;border:1px solid rgba(245,158,11,.30);border-radius:18px;overflow:hidden;">
                            <div style="height:4px;background-color:#f59e0b;line-height:4px;font-size:1px;">&nbsp;</div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding:40px 42px 24px;">
                                        <img src="{{ url('/images/sqa-shield-white.png') }}" alt="" width="64" style="display:block;width:64px;height:auto;margin-bottom:24px;border:0;">

                                        <div style="margin-bottom:12px;color:#f59e0b;font-size:11px;font-weight:700;letter-spacing:3px;text-transform:uppercase;">
                                            {{ $submission->is_recruitment ? 'RECLUTAMIENTO' : 'CONTACTO WEB' }}
                                        </div>

                                        <h1 style="margin:0 0 14px;color:#f7f7f8;font-size:30px;line-height:1.2;font-weight:800;">
                                            {{ $submission->is_recruitment ? 'Nueva solicitud de alistamiento' : 'Nueva consulta recibida' }}
                                        </h1>

                                        <p style="margin:0;color:#adb4c0;font-size:15px;line-height:1.7;">
                                            NewSlot ha recibido un nuevo mensaje de
                                            <strong style="color:#f59e0b;">{{ $submission->nickname }}</strong>.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 42px;">
                                        <div style="height:1px;background-color:#252b35;"></div>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:30px 42px 12px;">
                                        <div style="margin-bottom:14px;color:#f59e0b;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">
                                            Datos de contacto
                                        </div>

                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:separate;border-spacing:0 8px;">
                                            <tr>
                                                <td style="width:145px;padding:12px 14px;background:#090c11;border:1px solid #252b35;border-right:0;border-radius:8px 0 0 8px;color:#7f8998;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Nickname</td>
                                                <td style="padding:12px 14px;background:#090c11;border:1px solid #252b35;border-left:0;border-radius:0 8px 8px 0;color:#f7f7f8;font-size:14px;font-weight:700;">{{ $submission->nickname }}</td>
                                            </tr>
                                            <tr>
                                                <td style="width:145px;padding:12px 14px;background:#090c11;border:1px solid #252b35;border-right:0;border-radius:8px 0 0 8px;color:#7f8998;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Email</td>
                                                <td style="padding:12px 14px;background:#090c11;border:1px solid #252b35;border-left:0;border-radius:0 8px 8px 0;color:#f7f7f8;font-size:14px;">
                                                    <a href="mailto:{{ $submission->email }}" style="color:#f59e0b;text-decoration:none;">{{ $submission->email }}</a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="width:145px;padding:12px 14px;background:#090c11;border:1px solid #252b35;border-right:0;border-radius:8px 0 0 8px;color:#7f8998;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;">Recibido</td>
                                                <td style="padding:12px 14px;background:#090c11;border:1px solid #252b35;border-left:0;border-radius:0 8px 8px 0;color:#f7f7f8;font-size:14px;">{{ $submission->created_at?->format('d/m/Y H:i') }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:14px 42px 28px;">
                                        <div style="margin-bottom:12px;color:#f59e0b;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">Mensaje</div>
                                        <div style="padding:20px;background:#090c11;border:1px solid #252b35;border-radius:10px;color:#d8dde5;font-size:15px;line-height:1.75;white-space:normal;">
                                            {!! nl2br(e($submission->message)) !!}
                                        </div>
                                    </td>
                                </tr>

                                @if($submission->is_recruitment)
                                    <tr>
                                        <td style="padding:0 42px;">
                                            <div style="height:1px;background-color:#252b35;"></div>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding:30px 42px 26px;">
                                            <div style="margin-bottom:16px;color:#f59e0b;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">Requisitos de alistamiento</div>

                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;background:#090c11;border:1px solid #252b35;border-radius:10px;overflow:hidden;">
                                                @foreach([
                                                    'Normativa aceptada' => $submission->accepted_rules,
                                                    'Mayor de edad' => $submission->is_adult,
                                                    'Acepta aportaciones económicas' => $submission->accepts_contributions,
                                                    'Arma 3 + DLC/CDLC requeridos' => $submission->has_required_game_content,
                                                    'Disponibilidad los martes' => $submission->tuesday_available,
                                                    'Disponibilidad los viernes' => $submission->friday_available,
                                                    'Experiencia previa en simulación' => $submission->has_previous_experience,
                                                ] as $label => $value)
                                                    <tr>
                                                        <td style="padding:13px 16px;border-bottom:1px solid #202630;color:#adb4c0;font-size:13px;line-height:1.5;">{{ $label }}</td>
                                                        <td align="right" style="width:85px;padding:13px 16px;border-bottom:1px solid #202630;color:{{ $value ? '#86efac' : '#fca5a5' }};font-size:12px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;">
                                                            {{ $value ? 'SÍ' : 'NO' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td style="padding:0 42px;">
                                        <div style="height:1px;background-color:#252b35;"></div>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:30px 42px 18px;">
                                        <a href="mailto:{{ $submission->email }}?subject={{ rawurlencode('Squad ALPHA - ' . $submission->nickname) }}" style="display:inline-block;padding:15px 28px;background-color:#f59e0b;border-radius:8px;color:#05070a;font-size:13px;font-weight:800;letter-spacing:.5px;text-decoration:none;">
                                            RESPONDER A {{ strtoupper($submission->nickname) }}
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 42px 38px;">
                                        <p style="margin:0;color:#6f7888;font-size:12px;line-height:1.7;text-align:center;">
                                            El correo se ha enviado usando el sistema de correo de NewSlot. Al pulsar «Responder» en tu cliente de correo, la respuesta se dirigirá automáticamente a {{ $submission->email }}.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:28px 20px 0;">
                            <p style="margin:0 0 7px;color:#f59e0b;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">
                                REALISMO · DISCIPLINA · EQUIPO
                            </p>
                            <p style="margin:0;color:#6f7888;font-size:12px;line-height:1.6;">© {{ date('Y') }} Squad ALPHA · NewSlot</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
