<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Recupera tu contraseña | Squad ALPHA</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background-color: #05070a;
        font-family: Arial, Helvetica, sans-serif;
        color: #f7f7f8;
    "
>

    {{-- Preheader --}}
    <div
        style="
            display: none;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            color: transparent;
        "
    >
        Recupera el acceso a tu cuenta de Squad ALPHA.
    </div>

    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="
            width: 100%;
            background-color: #05070a;
        "
    >
        <tr>
            <td
                align="center"
                style="padding: 48px 20px;"
            >

                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 620px;
                    "
                >

                    {{-- Logo --}}
                    <tr>
                        <td
                            align="center"
                            style="padding-bottom: 28px;"
                        >
                            <img
                                src="{{ url('/images/sqa-header-logo.png') }}"
                                alt="Squad ALPHA"
                                width="180"
                                style="
                                    display: block;
                                    width: 180px;
                                    max-width: 100%;
                                    height: auto;
                                    border: 0;
                                "
                            >
                        </td>
                    </tr>

                    {{-- Tarjeta --}}
                    <tr>
                        <td
                            style="
                                background-color: #0e1219;
                                border: 1px solid rgba(245, 158, 11, 0.30);
                                border-radius: 18px;
                                overflow: hidden;
                            "
                        >

                            {{-- Línea superior --}}
                            <div
                                style="
                                    height: 4px;
                                    background-color: #f59e0b;
                                    line-height: 4px;
                                    font-size: 1px;
                                "
                            >
                                &nbsp;
                            </div>

                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                            >

                                {{-- Cabecera --}}
                                <tr>
                                    <td
                                        align="center"
                                        style="
                                            padding: 44px 42px 16px;
                                        "
                                    >

                                        <img
                                            src="{{ url('/images/sqa-shield-white.png') }}"
                                            alt=""
                                            width="72"
                                            style="
                                                display: block;
                                                width: 72px;
                                                height: auto;
                                                margin-bottom: 28px;
                                                border: 0;
                                            "
                                        >

                                        <div
                                            style="
                                                margin-bottom: 14px;
                                                color: #f59e0b;
                                                font-size: 12px;
                                                font-weight: 700;
                                                letter-spacing: 3px;
                                                text-transform: uppercase;
                                            "
                                        >
                                            SEGURIDAD DE LA CUENTA
                                        </div>

                                        <h1
                                            style="
                                                margin: 0 0 18px;
                                                color: #f7f7f8;
                                                font-size: 32px;
                                                line-height: 1.15;
                                                font-weight: 800;
                                            "
                                        >
                                            Recupera tu contraseña
                                        </h1>

                                        <p
                                            style="
                                                margin: 0;
                                                color: #adb4c0;
                                                font-size: 16px;
                                                line-height: 1.7;
                                            "
                                        >
                                            Hola
                                            <strong
                                                style="color: #f59e0b;"
                                            >
                                                {{ $user->nick }}
                                            </strong>,
                                        </p>

                                        <p
                                            style="
                                                margin: 14px 0 0;
                                                color: #adb4c0;
                                                font-size: 16px;
                                                line-height: 1.7;
                                            "
                                        >
                                            Hemos recibido una solicitud
                                            para restablecer la contraseña
                                            de tu cuenta de Squad ALPHA.
                                        </p>

                                        <p
                                            style="
                                                margin: 14px 0 0;
                                                color: #adb4c0;
                                                font-size: 14px;
                                                line-height: 1.7;
                                            "
                                        >
                                            El enlace será válido durante

                                            <strong
                                                style="color: #f59e0b;"
                                            >
                                                {{ $expireMinutes }} minutos
                                            </strong>.
                                        </p>

                                    </td>
                                </tr>

                                {{-- Botón --}}
                                <tr>
                                    <td
                                        align="center"
                                        style="
                                            padding: 22px 42px 34px;
                                        "
                                    >

                                        <a
                                            href="{{ $resetUrl }}"
                                            style="
                                                display: inline-block;
                                                padding: 16px 30px;
                                                background-color: #f59e0b;
                                                border-radius: 8px;
                                                color: #05070a;
                                                font-size: 14px;
                                                font-weight: 800;
                                                letter-spacing: 0.5px;
                                                text-decoration: none;
                                            "
                                        >
                                            RESTABLECER CONTRASEÑA
                                        </a>

                                    </td>
                                </tr>

                                {{-- Separador --}}
                                <tr>
                                    <td
                                        style="
                                            padding: 0 42px;
                                        "
                                    >
                                        <div
                                            style="
                                                height: 1px;
                                                background-color: #252b35;
                                            "
                                        ></div>
                                    </td>
                                </tr>

                                {{-- Aviso --}}
                                <tr>
                                    <td
                                        style="
                                            padding: 30px 42px 42px;
                                        "
                                    >

                                        <p
                                            style="
                                                margin: 0 0 10px;
                                                color: #adb4c0;
                                                font-size: 13px;
                                                line-height: 1.6;
                                            "
                                        >
                                            Si no has solicitado un cambio
                                            de contraseña, puedes ignorar
                                            este correo. Tu contraseña
                                            actual seguirá funcionando.
                                        </p>

                                        <p
                                            style="
                                                margin: 0 0 18px;
                                                color: #6f7888;
                                                font-size: 12px;
                                                line-height: 1.6;
                                            "
                                        >
                                            Si el botón no funciona, copia
                                            y pega este enlace en tu
                                            navegador:
                                        </p>

                                        <p
                                            style="
                                                margin: 0;
                                                padding: 13px 15px;
                                                background-color: #090c11;
                                                border: 1px solid #252b35;
                                                border-radius: 7px;
                                                color: #8f98a8;
                                                font-size: 11px;
                                                line-height: 1.6;
                                                word-break: break-all;
                                            "
                                        >
                                            {{ $resetUrl }}
                                        </p>

                                    </td>
                                </tr>

                            </table>

                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td
                            align="center"
                            style="
                                padding: 28px 20px 0;
                            "
                        >

                            <p
                                style="
                                    margin: 0 0 7px;
                                    color: #f59e0b;
                                    font-size: 11px;
                                    font-weight: 700;
                                    letter-spacing: 2px;
                                    text-transform: uppercase;
                                "
                            >
                                REALISMO · DISCIPLINA · EQUIPO
                            </p>

                            <p
                                style="
                                    margin: 0;
                                    color: #6f7888;
                                    font-size: 12px;
                                    line-height: 1.6;
                                "
                            >
                                © {{ date('Y') }} Squad ALPHA
                            </p>

                            <p
                                style="
                                    margin: 4px 0 0;
                                    color: #505866;
                                    font-size: 11px;
                                "
                            >
                                Este mensaje ha sido enviado
                                automáticamente.
                                No respondas a este correo.
                            </p>

                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>