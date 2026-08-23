<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Restablecer contraseña - Squad ALPHA
    </title>

    <link
        rel="stylesheet"
        href="{{ asset('css/landing.css') }}"
    >
</head>

<body class="landing-body">

    <div
        class="auth-modal is-open"
        style="display: flex;"
    >

        <div class="auth-modal__backdrop"></div>

        <div class="auth-modal__panel">

            <a
                href="{{ route('home') }}"
                class="auth-modal__close"
                aria-label="Volver al inicio"
                style="text-decoration: none;"
            >
                ×
            </a>

            <div class="auth-modal__header">

                <span class="auth-modal__eyebrow">
                    Seguridad de la cuenta
                </span>

                <h2>
                    Nueva contraseña
                </h2>

                <p>
                    Introduce una nueva contraseña
                    para recuperar el acceso a tu cuenta.
                </p>

            </div>

            <form
                method="POST"
                action="{{ route('password.update') }}"
                class="auth-form"
            >
                @csrf

                <input
                    type="hidden"
                    name="token"
                    value="{{ $token }}"
                >

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

                    @error('email')
                        <span class="auth-form__error">
                            {{ $message }}
                        </span>
                    @enderror

                </div>

                <div class="auth-form__group">

                    <label for="reset-password">
                        Nueva contraseña
                    </label>

                    <input
                        id="reset-password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        required
                    >

                    @error('password')
                        <span class="auth-form__error">
                            {{ $message }}
                        </span>
                    @enderror

                </div>

                <div class="auth-form__group">

                    <label
                        for="
                            reset-password-confirmation
                        "
                    >
                        Repetir nueva contraseña
                    </label>

                    <input
                        id="reset-password-confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="
                        btn
                        btn-primary
                        btn-lg
                        auth-form__submit
                    "
                >
                    Cambiar contraseña
                </button>

            </form>

        </div>

    </div>

</body>
</html>