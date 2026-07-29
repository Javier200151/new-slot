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