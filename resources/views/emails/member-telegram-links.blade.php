<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }} | Squad ALPHA</title>
</head>
<body style="margin:0;background:#05070a;color:#e5e7eb;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#05070a;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:680px;background:#0b0f14;border:1px solid #252b35;border-radius:14px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 36px;background:#080b10;border-bottom:1px solid #252b35;">
                        <div style="color:#f59e0b;font-size:12px;font-weight:800;letter-spacing:2px;text-transform:uppercase;">Squad ALPHA</div>
                        <h1 style="margin:8px 0 8px;color:#fff;font-size:24px;line-height:1.25;">
                            {{ $reactivation ? 'Bienvenido de vuelta' : 'Bienvenido a Squad ALPHA' }}
                        </h1>
                        <p style="margin:0;color:#adb4c0;font-size:14px;line-height:1.7;">
                            {!! nl2br(e($mailBody)) !!}
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:26px 36px 12px;">
                        <div style="margin-bottom:14px;color:#f59e0b;font-size:11px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">Grupos oficiales de Telegram</div>

                        @foreach($links as $label => $url)
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 12px;background:#090c11;border:1px solid #252b35;border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px;color:#f7f7f8;font-size:14px;font-weight:700;">{{ $label }}</td>
                                    <td align="right" style="padding:14px 16px;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:9px 14px;background:#f59e0b;color:#05070a;text-decoration:none;border-radius:7px;font-size:12px;font-weight:800;">Unirme</a>
                                    </td>
                                </tr>
                            </table>
                        @endforeach
                    </td>
                </tr>

                <tr>
                    <td style="padding:10px 36px 28px;color:#7f8998;font-size:12px;line-height:1.7;">
                        Si alguno de los enlaces ya no es válido, contacta con el equipo de Squad ALPHA para recibir uno actualizado.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
