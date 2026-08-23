<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>Restablecer contraseña - Squad ALPHA</title>

    <link
        rel="stylesheet"
        href="{{ asset('css/landing.css') }}"
    >
</head>

<body
    class="landing-body"
    style="
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    "
>

    <main
        style="
            width: 100%;
            max-width: 520px;
        "
    >

        {{-- Logo --}}
        <div
            style="
                display: flex;
                justify-content: center;
                margin-bottom: 28px;
            "
        >
            <a
                href="{{ route('home') }}"
                style="
                    display: inline-flex;
                    text-decoration: none;
                "
            >
                <img
                    src="{{ asset('images/sqa-header-logo.png') }}"
                    alt="Squad ALPHA"
                    style="
                        width: 190px;
                        max-width: 100%;
                        height: auto;
                    "
                >
            </a>
        </div>

        {{-- Tarjeta --}}
        <section
            style="
                position: relative;
                overflow: hidden;
                padding: 38px;
                border: 1px solid rgba(245, 158, 11, 0.28);
                border-radius: 18px;
                background:
                    radial-gradient(
                        circle at 90% 0%,
                        rgba(245, 158, 11, 0.08),
                        transparent 35%
                    ),
                    #0e1219;
                box-shadow:
                    0 24px 70px rgba(0, 0, 0, 0.45);
            "
        >

            {{-- Línea naranja superior --}}
            <div
                style="
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 3px;
                    background: linear-gradient(
                        90deg,
                        #f59e0b,
                        rgba(245, 158, 11, 0.15)
                    );
                "
            ></div>

            {{-- Cabecera --}}
            <div
                style="
                    margin-bottom: 30px;
                "
            >

                <span
                    style="
                        display: block;
                        margin-bottom: 10px;
                        color: #f59e0b;
                        font-size: 0.72rem;
                        font-weight: 800;
                        letter-spacing: 2px;
                        text-transform: uppercase;
                    "
                >
                    Seguridad de la cuenta
                </span>

                <h1
                    style="
                        margin: 0 0 12px;
                        color: #f7f7f8;
                        font-size: 2rem;
                        line-height: 1.15;
                        font-weight: 800;
                    "
                >
                    Nueva contraseña
                </h1>

                <p
                    style="
                        margin: 0;
                        color: #929bad;
                        font-size: 0.95rem;
                        line-height: 1.65;
                    "
                >
                    Introduce una nueva contraseña para recuperar
                    el acceso a tu cuenta de Squad ALPHA.
                </p>

            </div>

            {{-- Errores generales --}}
            @if ($errors->any())
                <div
                    style="
                        margin-bottom: 22px;
                        padding: 14px 16px;
                        border: 1px solid rgba(239, 68, 68, 0.35);
                        border-radius: 10px;
                        background: rgba(239, 68, 68, 0.08);
                        color: #fecaca;
                        font-size: 0.86rem;
                        line-height: 1.55;
                    "
                >
                    No hemos podido cambiar la contraseña.
                    Revisa los datos introducidos.
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('password.update') }}"
                class="auth-form"
            >
                @csrf

                {{-- Token de recuperación --}}
                <input
                    type="hidden"
                    name="token"
                    value="{{ $token }}"
                >

                {{-- Email --}}
                <div class="auth-form__group">

                    <label for="reset-email">
                        Correo electrónico
                    </label>

                    <input
                        id="reset-email"
                        type="email"
                        name="email"
                        value="{{ old('email', $email) }}"
                        autocomplete="email"
                        required
                        readonly
                    >

                    <small
                        style="
                            display: block;
                            margin-top: 7px;
                            color: #707b90;
                            font-size: 0.78rem;
                            line-height: 1.45;
                        "
                    >
                        Esta es la cuenta asociada al enlace
                        de recuperación recibido.
                    </small>

                    @error('email')
                        <span
                            class="auth-form__error"
                            style="
                                display: block;
                                margin-top: 7px;
                            "
                        >
                            {{ $message }}
                        </span>
                    @enderror

                </div>

                {{-- Nueva contraseña --}}
                <div class="auth-form__group">

                    <label for="reset-password">
                        Nueva contraseña
                    </label>

                    <input
                        id="reset-password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                        autofocus
                    >

                    <small
                        style="
                            display: block;
                            margin-top: 7px;
                            color: #707b90;
                            font-size: 0.78rem;
                            line-height: 1.45;
                        "
                    >
                        Debe contener al menos 8 caracteres.
                    </small>

                    @error('password')
                        <span
                            class="auth-form__error"
                            style="
                                display: block;
                                margin-top: 7px;
                            "
                        >
                            {{ $message }}
                        </span>
                    @enderror

                </div>

                {{-- Confirmación --}}
                <div class="auth-form__group">

                    <label for="reset-password-confirmation">
                        Repetir nueva contraseña
                    </label>

                    <input
                        id="reset-password-confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >

                </div>

                {{-- Botón --}}
                <button
                    type="submit"
                    class="
                        btn
                        btn-primary
                        btn-lg
                        auth-form__submit
                    "
                    style="
                        width: 100%;
                        margin-top: 8px;
                    "
                >
                    Cambiar contraseña
                </button>

            </form>

            {{-- Volver --}}
            <div
                style="
                    margin-top: 25px;
                    padding-top: 22px;
                    border-top: 1px solid rgba(255, 255, 255, 0.07);
                    text-align: center;
                "
            >
                <span
                    style="
                        color: #7f899b;
                        font-size: 0.84rem;
                    "
                >
                    ¿Ya recuerdas tu contraseña?
                </span>

                <a
                    href="{{ route('home', ['modal' => 'login']) }}"
                    style="
                        margin-left: 5px;
                        color: #f59e0b;
                        font-size: 0.84rem;
                        font-weight: 800;
                        text-decoration: none;
                    "
                >
                    Iniciar sesión
                </a>
            </div>

        </section>

        {{-- Pie --}}
        <div
            style="
                margin-top: 25px;
                text-align: center;
            "
        >
            <p
                style="
                    margin: 0 0 5px;
                    color: #f59e0b;
                    font-size: 0.67rem;
                    font-weight: 800;
                    letter-spacing: 2px;
                    text-transform: uppercase;
                "
            >
                Realismo · Disciplina · Equipo
            </p>

            <p
                style="
                    margin: 0;
                    color: #525b6b;
                    font-size: 0.72rem;
                "
            >
                © {{ date('Y') }} Squad ALPHA
            </p>
        </div>

    </main>

</body>
</html>