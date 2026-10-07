<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mailSubject }} | Squad ALPHA</title>
</head>
<body style="margin:0;padding:0;background-color:#05070a;font-family:Arial,Helvetica,sans-serif;color:#f7f7f8;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#05070a;">
        <tr>
            <td align="center" style="padding:48px 20px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:620px;">
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
                                    <td align="center" style="padding:44px 42px 18px;">
                                        <img src="{{ url('/images/sqa-shield-white.png') }}" alt="" width="72" style="display:block;width:72px;height:auto;margin-bottom:28px;border:0;">

                                        <div style="margin-bottom:14px;color:#f59e0b;font-size:12px;font-weight:700;letter-spacing:3px;text-transform:uppercase;">
                                            {{ $eyebrow }}
                                        </div>

                                        <h1 style="margin:0 0 20px;color:#f7f7f8;font-size:32px;line-height:1.15;font-weight:800;">
                                            {{ $headline }}
                                        </h1>

                                        <div style="color:#adb4c0;font-size:16px;line-height:1.7;text-align:left;">
                                            {!! nl2br(e($mailBody)) !!}
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:8px 42px 12px;">
                                        <div style="margin-bottom:16px;color:#f59e0b;font-size:11px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;">
                                            GRUPOS OFICIALES DE TELEGRAM
                                        </div>

                                        @foreach($links as $label => $url)
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 12px;background-color:#090c11;border:1px solid #252b35;border-radius:9px;">
                                                <tr>
                                                    <td style="padding:15px 16px;color:#f7f7f8;font-size:14px;font-weight:700;">{{ $label }}</td>
                                                    <td align="right" style="padding:12px 16px;">
                                                        <a href="{{ $url }}" style="display:inline-block;padding:10px 16px;background-color:#f59e0b;border-radius:7px;color:#05070a;font-size:12px;font-weight:800;text-decoration:none;">
                                                            UNIRME
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endforeach
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:10px 42px 0;">
                                        <div style="height:1px;background-color:#252b35;"></div>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:28px 42px 42px;">
                                        <p style="margin:0;color:#adb4c0;font-size:13px;line-height:1.7;">
                                            Si alguno de los enlaces ya no es válido, contacta con el equipo de Squad ALPHA para recibir uno actualizado.
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
                            <p style="margin:0;color:#6f7888;font-size:12px;line-height:1.6;">© {{ date('Y') }} Squad ALPHA</p>
                            <p style="margin:4px 0 0;color:#505866;font-size:11px;">
                                Este mensaje ha sido enviado automáticamente. No respondas a este correo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
