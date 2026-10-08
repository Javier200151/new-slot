<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mailSubject }} | Squad ALPHA</title>
    <style>
        @media only screen and (max-width: 620px) {
            .sqa-mail-shell { width: 100% !important; }
            .sqa-hero-art, .sqa-hero-copy, .sqa-step-cell, .sqa-group-cell { display: block !important; width: 100% !important; }
            .sqa-hero-art { border-right: 0 !important; border-bottom: 1px solid #1f2937 !important; }
            .sqa-hero-copy { padding: 30px 24px !important; }
            .sqa-step-cell { margin-bottom: 10px !important; }
            .sqa-group-cell { padding: 14px 10px !important; }
            .sqa-mail-title { font-size: 26px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#05070a;font-family:Arial,Helvetica,sans-serif;color:#f8fafc;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#05070a;">
        <tr>
            <td align="center" style="padding:36px 16px 50px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="sqa-mail-shell" style="width:100%;max-width:720px;">
                    <tr>
                        <td style="padding:0 4px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left">
                                        <img src="{{ url('/images/sqa-header-logo.png') }}" alt="Squad ALPHA" width="190" style="display:block;width:190px;max-width:100%;height:auto;border:0;">
                                    </td>
                                    <td align="right" style="color:#6b7280;font-size:11px;letter-spacing:1.5px;white-space:nowrap;">EST. 2012</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#090d12;border:1px solid #1f2937;border-radius:18px;overflow:hidden;">
                            <div style="height:4px;background:#f5a900;line-height:4px;font-size:1px;">&nbsp;</div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td class="sqa-hero-art" width="38%" valign="middle" align="center" style="padding:42px 24px;background:#080b10;border-right:1px solid #1f2937;">
                                        <div style="width:150px;height:150px;border:1px solid #26303d;border-radius:999px;background:#0d131b;text-align:center;line-height:150px;">
                                            <img src="{{ url('/images/sqa-shield-white.png') }}" alt="" width="78" style="display:inline-block;vertical-align:middle;width:78px;height:auto;border:0;">
                                        </div>
                                        <div style="margin-top:20px;color:#f5a900;font-size:10px;font-weight:800;letter-spacing:2.4px;text-transform:uppercase;">SQUAD ALPHA</div>
                                    </td>
                                    <td class="sqa-hero-copy" width="62%" valign="middle" style="padding:42px 38px;">
                                        <div style="margin-bottom:12px;color:#f5a900;font-size:11px;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;">{{ $eyebrow }}</div>
                                        <h1 class="sqa-mail-title" style="margin:0 0 18px;color:#ffffff;font-size:31px;line-height:1.08;font-weight:900;text-transform:uppercase;">{{ $headline }}</h1>
                                        <div style="color:#c8ced8;font-size:14px;line-height:1.72;">{!! nl2br(e($mailBody)) !!}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:42px 4px 16px;">
                            <div style="color:#f5a900;font-size:11px;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;margin-bottom:8px;">SIGUIENTE PASO</div>
                            <h2 style="margin:0;color:#ffffff;font-size:28px;line-height:1.15;font-weight:900;text-transform:uppercase;">PASOS QUE DEBES COMPLETAR</h2>
                            <p style="margin:13px 0 0;color:#9ca3af;font-size:14px;line-height:1.65;">{{ $sectionIntro }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 0 36px;">
                            <table role="presentation" width="100%" cellspacing="10" cellpadding="0" border="0">
                                <tr>
                                    @foreach($steps as $step)
                                        <td class="sqa-step-cell" width="33.33%" valign="top" style="background:#f5bd3d;border-radius:3px;padding:24px 18px;color:#10151d;text-align:center;">
                                            <div style="font-size:18px;font-weight:900;letter-spacing:.8px;">{{ $step['title'] }}</div>
                                            <div style="margin:18px auto 16px;width:58px;height:58px;border:2px solid #17202c;border-radius:999px;line-height:54px;font-size:26px;font-weight:900;">{{ $step['icon'] }}</div>
                                            <div style="font-size:12px;line-height:1.5;font-weight:600;">{{ $step['text'] }}</div>
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 4px 16px;">
                            <div style="color:#f5a900;font-size:11px;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;margin-bottom:8px;">TELEGRAM</div>
                            <h2 style="margin:0;color:#ffffff;font-size:28px;line-height:1.15;font-weight:900;text-transform:uppercase;">PONTE EN CONTACTO</h2>
                            <p style="margin:13px 0 0;color:#9ca3af;font-size:14px;line-height:1.65;">Únete a los grupos oficiales para mantenerte al día con la comunidad y sus comunicaciones.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 0 10px;">
                            <table role="presentation" width="100%" cellspacing="10" cellpadding="0" border="0">
                                <tr>
                                    @foreach($links as $label => $url)
                                        @php
                                            $description = match ($label) {
                                                'ALPHA Cantina' => 'Grupo informal',
                                                'ALPHA Oficial' => 'Grupo serio',
                                                default => 'Canal de difusión',
                                            };
                                            $displayLabel = $label === '= ALPHA FORCE NETWORK =' ? 'ALPHA FORCE NETWORK' : $label;
                                        @endphp
                                        <td class="sqa-group-cell" width="33.33%" valign="top" align="center" style="padding:18px 10px 12px;">
                                            <div style="width:42px;height:42px;border-radius:999px;background:#229ed9;color:#ffffff;line-height:42px;font-size:20px;font-weight:900;margin:0 auto 12px;">↗</div>
                                            <div style="min-height:36px;color:#f5a900;font-size:14px;font-weight:900;line-height:1.25;text-transform:uppercase;">{{ $displayLabel }}</div>
                                            <div style="margin-top:8px;color:#d1d5db;font-size:11px;line-height:1.4;">{{ $description }}</div>
                                            <a href="{{ $url }}" style="display:inline-block;margin-top:12px;padding:9px 12px;border:1px solid #374151;border-radius:6px;color:#ffffff;font-size:10px;font-weight:800;text-decoration:none;text-transform:uppercase;">Abrir invitación</a>
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 4px 0;">
                            <div style="height:1px;background:#1f2937;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:26px 20px 0;">
                            <img src="{{ url('/images/sqa-shield-white.png') }}" alt="Squad ALPHA" width="48" style="display:block;width:48px;height:auto;margin:0 auto 12px;border:0;">
                            <p style="margin:0 0 6px;color:#f5a900;font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase;">REALISMO · DISCIPLINA · EQUIPO</p>
                            <p style="margin:0;color:#6b7280;font-size:11px;line-height:1.6;">© {{ date('Y') }} Squad ALPHA · Mensaje automático</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
