@guest
    {{-- Modal login --}}
    <div
        id="login-modal"
        class="auth-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="login-modal-title"
        hidden
    >
        <div class="auth-modal__backdrop" data-close-modal></div>

        <div class="auth-modal__panel">
            <button
                type="button"
                class="auth-modal__close"
                data-close-modal
                aria-label="Cerrar"
            >
                ×
            </button>

            <div class="auth-modal__header">
                <span class="auth-modal__eyebrow">Acceso de miembros</span>
                <h2 id="login-modal-title">Iniciar sesión</h2>
                <p>Accede a tu cuenta de Squad ALPHA.</p>
            </div>

            <form
                method="POST"
                action="{{ route('public.login') }}"
                class="auth-form"
            >
                @csrf
                <input type="hidden" name="auth_form" value="login">

                <div class="auth-form__group">
                    <label for="login-email">Email</label>
                    <input
                        id="login-email"
                        type="email"
                        name="email"
                        value="{{ old('auth_form') === 'login' ? old('email') : '' }}"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="auth-form__group">
                    <label for="login-password">Contraseña</label>
                    <input
                        id="login-password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <label class="auth-form__checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span>Recordarme</span>
                </label>
                <div
                    style="
                        display: flex;
                        justify-content: flex-end;
                        margin-top: -8px;
                    "
                >
                    <button
                        type="button"
                        data-switch-modal="forgot-password-modal"
                        style="
                            padding: 0;
                            border: 0;
                            background: none;
                            color: var(--primary);
                            font-size: 0.86rem;
                            font-weight: 800;
                            cursor: pointer;
                        "
                    >
                        ¿Has olvidado tu contraseña?
                    </button>
                </div>
                @if(session('status') === 'password-reset')
                    <div
                        style="
                            padding: 13px 15px;
                            border: 1px solid rgba(34, 197, 94, 0.35);
                            border-radius: 9px;
                            background: rgba(34, 197, 94, 0.08);
                            color: #bbf7d0;
                            font-size: 0.86rem;
                        "
                    >
                        Contraseña actualizada correctamente.
                        Ya puedes iniciar sesión.
                    </div>
                @endif
                <button
                    type="submit"
                    class="btn btn-primary btn-lg auth-form__submit"
                >
                    Iniciar sesión
                </button>
            </form>

            <div class="auth-modal__switch">
                <span>¿No tienes cuenta?</span>
                <button type="button" data-switch-modal="register-modal">
                    Crear cuenta
                </button>
            </div>
        </div>
    </div>
    {{-- Modal recuperación de contraseña --}}
    <div
        id="forgot-password-modal"
        class="auth-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="forgot-password-modal-title"
        hidden
    >
        <div
            class="auth-modal__backdrop"
            data-close-modal
        ></div>

        <div class="auth-modal__panel">

            <button
                type="button"
                class="auth-modal__close"
                data-close-modal
                aria-label="Cerrar"
            >
                ×
            </button>

            <div class="auth-modal__header">

                <span class="auth-modal__eyebrow">
                    Recuperar acceso
                </span>

                <h2 id="forgot-password-modal-title">
                    Recuperar contraseña
                </h2>

                <p>
                    Introduce el correo asociado a tu cuenta
                    de Squad ALPHA.
                </p>

            </div>

            @if(
                session('status')
                === 'password-reset-link-sent'
            )
                <div
                    style="
                        margin-bottom: 20px;
                        padding: 14px 16px;
                        border: 1px solid
                            rgba(34, 197, 94, 0.35);
                        border-radius: 10px;
                        background:
                            rgba(34, 197, 94, 0.08);
                        color: #bbf7d0;
                        font-size: 0.87rem;
                        line-height: 1.5;
                    "
                >
                    Si existe una cuenta asociada a ese
                    correo, recibirás un enlace para
                    restablecer tu contraseña.
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('password.email') }}"
                class="auth-form"
            >
                @csrf

                <input
                    type="hidden"
                    name="auth_form"
                    value="forgot-password"
                >

                <div class="auth-form__group">

                    <label for="forgot-password-email">
                        Correo electrónico
                    </label>

                    <input
                        id="forgot-password-email"
                        type="email"
                        name="email"
                        value="{{
                            old('auth_form')
                                === 'forgot-password'
                                    ? old('email')
                                    : ''
                        }}"
                        autocomplete="email"
                        required
                    >

                    @error('email', 'forgotPassword')
                        <span class="auth-form__error">
                            {{ $message }}
                        </span>
                    @enderror

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
                    Enviar enlace de recuperación
                </button>

            </form>

            <div class="auth-modal__switch">

                <span>
                    ¿Recuerdas tu contraseña?
                </span>

                <button
                    type="button"
                    data-switch-modal="login-modal"
                >
                    Iniciar sesión
                </button>

            </div>

        </div>
    </div>
    {{-- Modal registro --}}
    <div
        id="register-modal"
        class="auth-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="register-modal-title"
        hidden
    >
        <div class="auth-modal__backdrop" data-close-modal></div>

        <div class="auth-modal__panel">
            <button
                type="button"
                class="auth-modal__close"
                data-close-modal
                aria-label="Cerrar"
            >
                ×
            </button>

            <div class="auth-modal__header">
                <span class="auth-modal__eyebrow">Reclutamiento</span>
                <h2 id="register-modal-title">Crear una cuenta</h2>
                <p>Comienza tu recorrido dentro de Squad ALPHA.</p>
            </div>

            <form
                method="POST"
                action="{{ route('public.register.store') }}"
                class="auth-form"
            >
                @csrf
                <input type="hidden" name="auth_form" value="register">

                <div class="auth-form__group">
                    <label for="register-nick">Nick</label>
                    <input
                        id="register-nick"
                        type="text"
                        name="nick"
                        value="{{ old('auth_form') === 'register' ? old('nick') : '' }}"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="auth-form__group">
                    <label for="register-email">Email</label>
                    <input
                        id="register-email"
                        type="email"
                        name="email"
                        value="{{ old('auth_form') === 'register' ? old('email') : '' }}"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="auth-form__columns">
                    <div class="auth-form__group">
                        <label for="register-password">Contraseña</label>
                        <input
                            id="register-password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="auth-form__group">
                        <label for="register-password-confirmation">Repetir contraseña</label>
                        <input
                            id="register-password-confirmation"
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary btn-lg auth-form__submit"
                >
                    Crear cuenta
                </button>
            </form>

            <div class="auth-modal__switch">
                <span>¿Ya tienes cuenta?</span>
                <button type="button" data-switch-modal="login-modal">
                    Iniciar sesión
                </button>
            </div>
        </div>
    </div>
@endguest