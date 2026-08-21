@php
    $avatarUrl = $user->image
        ? asset('storage/' . $user->image)
        : asset('images/sqa-shield-white.png');

    $statusMessage = match (session('status')) {
        'profile-updated' => 'Tu perfil se ha actualizado correctamente.',
        'profile-updated-email-changed' => 'Tu perfil se ha actualizado. Hemos enviado un enlace de verificación al nuevo correo.',
        'password-updated' => 'Tu contraseña se ha actualizado correctamente.',
        'image-deleted' => 'La imagen de perfil se ha eliminado.',
        'verification-link-sent' => 'Te hemos enviado un nuevo enlace de verificación.',
        'email-verified' => 'Tu correo electrónico se ha verificado correctamente.',
        'email-already-verified' => 'Tu correo electrónico ya estaba verificado.',
        default => null,
    };
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Mi perfil - Squad ALPHA</title>

    <link
        rel="stylesheet"
        href="{{ asset('css/landing.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/profile.css') }}"
    >
</head>

<body class="landing-body">

    <header class="landing-header">
        <div class="container nav-wrapper">

            <a
                href="{{ route('home') }}"
                class="brand brand--image"
                aria-label="Volver al inicio"
            >
                <img
                    src="{{ asset('images/sqa-header-logo.png') }}"
                    alt="Squad ALPHA"
                    class="brand-logo-image"
                >
            </a>

            <div class="nav-actions">
                @include(
                    'partials.notification-bell'
                )
                <a
                    href="{{ route('home') }}"
                    class="btn btn-outline"
                >
                    Volver al inicio
                </a>

                @if(
                    $user->hasRole('admin')
                    || $user->can('filament.access')
                )
                    <a
                        href="{{ url('/admin') }}"
                        class="btn btn-outline"
                    >
                        Panel de administración
                    </a>
                @endif

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                    class="logout-form"
                >
                    @csrf

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Cerrar sesión
                    </button>
                </form>
            </div>

        </div>
    </header>

    <main class="profile-page">
        <div class="container profile-container">

            <header class="profile-heading">
                <div>
                    <span class="section-label">
                        Área personal
                    </span>

                    <h1>Mi perfil</h1>

                    <p>
                        Gestiona tus datos personales, tu imagen,
                        tu correo y tu contraseña.
                    </p>
                </div>

                <div class="profile-session">
                    <span></span>
                    Sesión iniciada como
                    <strong>{{ $user->nick }}</strong>
                </div>
            </header>

            @if($statusMessage)
                <div class="profile-alert profile-alert--success">
                    {{ $statusMessage }}
                </div>
            @endif

            @if(session('warning'))
                <div class="profile-alert profile-alert--warning">
                    {{ session('warning') }}
                </div>
            @endif

            @if(! $user->hasVerifiedEmail())
                <section class="verification-card">
                    <div>
                        <span class="verification-card__icon">!</span>

                        <div>
                            <h2>Correo pendiente de verificación</h2>

                            <p>
                                Debes verificar
                                <strong>{{ $user->email }}</strong>.
                                Revisa tu bandeja de entrada y la carpeta de spam.
                            </p>
                        </div>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('verification.send') }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="btn btn-outline"
                        >
                            Reenviar verificación
                        </button>
                    </form>
                </section>
            @endif

            <div class="profile-layout">

                <aside class="profile-sidebar">

                    <section class="profile-card profile-identity">
                        <div class="profile-avatar">
                            <img
                                src="{{ $avatarUrl }}"
                                alt="Imagen de perfil de {{ $user->nick }}"
                            >
                        </div>

                        <h2>{{ $user->nick }}</h2>
                        <p>{{ $user->email }}</p>

                        @if($user->hasVerifiedEmail())
                            <span class="profile-badge profile-badge--verified">
                                Correo verificado
                            </span>
                        @else
                            <span class="profile-badge profile-badge--pending">
                                Correo sin verificar
                            </span>
                        @endif

                        @if($user->image)
                            <form
                                method="POST"
                                action="{{ route('profile.image.delete') }}"
                                class="delete-image-form"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="profile-text-button"
                                    onclick="return confirm('¿Eliminar la imagen de perfil?')"
                                >
                                    Eliminar imagen
                                </button>
                            </form>
                        @endif
                    </section>
                    @if($user->status?->name !== 'USUARIO')
                        <section class="profile-signature-card">

                            <div class="profile-signature-card__header">
                                <div>
                                    <span>FIRMA SQA</span>
                                    <strong>Mi firma</strong>
                                </div>

                                <a
                                    href="{{ $user->getSignatureUrl() }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Abrir
                                </a>
                            </div>

                            <div class="profile-signature-card__preview">
                                <iframe
                                    src="{{ $user->getSignatureUrl() }}"
                                    title="Firma de {{ $user->nick }}"
                                    class="profile-signature-card__iframe"
                                    scrolling="no"
                                ></iframe>
                            </div>

                        </section>
                    @endif
                    <section class="profile-card">
                        <header class="profile-card__header">
                            <span>Información SQA</span>
                            <h2>Datos internos</h2>
                        </header>

                        <dl class="readonly-list">
                            <div>
                                <dt>Promo</dt>
                                <dd>
                                    {{ $user->promo_id
                                        ? 'Promo #' . $user->promo_id
                                        : 'Sin promo'
                                    }}
                                </dd>
                            </div>

                            <div>
                                <dt>Estado</dt>
                                <dd>
                                    {{ $user->status?->name ?? 'Sin estado' }}
                                </dd>
                            </div>

                            <div>
                                <dt>Fecha de ingreso</dt>
                                <dd>
                                    {{ $user->member_at?->format('d/m/Y')
                                        ?? 'No indicada'
                                    }}
                                </dd>
                            </div>

                            <div>
                                <dt>Tutor</dt>
                                <dd>
                                    {{ $user->tutor?->nick
                                        ?? 'Sin tutor asignado'
                                    }}
                                </dd>
                            </div>

                            <div>
                                <dt>Cuenta creada</dt>
                                <dd>
                                    {{ $user->created_at?->format('d/m/Y H:i')
                                        ?? 'No disponible'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                </aside>

                <div class="profile-content">

                    <section class="profile-card">
                        <header class="profile-card__header">
                            <span>Datos personales</span>
                            <h2>Editar perfil</h2>

                            <p>
                                Solo puedes modificar los datos de tu propia cuenta.
                            </p>
                        </header>

                        <form
                            method="POST"
                            action="{{ route('profile.update') }}"
                            enctype="multipart/form-data"
                            class="profile-form"
                        >
                            @csrf
                            @method('PATCH')

                            <div class="profile-form__columns">
                                <div class="profile-field">
                                    <label for="nick">Nick</label>

                                    <input
                                        id="nick"
                                        name="nick"
                                        type="text"
                                        value="{{ old('nick', $user->nick) }}"
                                        required
                                    >

                                    @error('nick', 'profileUpdate')
                                        <span class="profile-error">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>

                                <div class="profile-field">
                                    <label for="email">
                                        Correo electrónico
                                    </label>

                                    <input
                                        id="email"
                                        name="email"
                                        type="email"
                                        value="{{ old('email', $user->email) }}"
                                        required
                                    >

                                    <small>
                                        Al cambiarlo tendrás que verificarlo de nuevo.
                                    </small>

                                    @error('email', 'profileUpdate')
                                        <span class="profile-error">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="profile-field">
                                <label for="quote">
                                    Frase personal
                                </label>

                                <textarea
                                    id="quote"
                                    name="quote"
                                    rows="4"
                                    maxlength="500"
                                >{{ old('quote', $user->quote) }}</textarea>

                                @error('quote', 'profileUpdate')
                                    <span class="profile-error">
                                        {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            <div class="profile-form__columns">
                                <div class="profile-field">
                                    <label for="discord_id">
                                        Discord
                                    </label>

                                    <input
                                        id="discord_id"
                                        name="discord_id"
                                        type="text"
                                        value="{{ old(
                                            'discord_id',
                                            $user->discord_id
                                        ) }}"
                                    >

                                    @error('discord_id', 'profileUpdate')
                                        <span class="profile-error">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>

                                <div class="profile-field">
                                    <label for="steam_id">
                                        Steam ID
                                    </label>

                                    <input
                                        id="steam_id"
                                        name="steam_id"
                                        type="text"
                                        value="{{ old(
                                            'steam_id',
                                            $user->steam_id
                                        ) }}"
                                    >

                                    @error('steam_id', 'profileUpdate')
                                        <span class="profile-error">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="profile-form__columns">
                                <div class="profile-field">
                                    <label for="birth_at">
                                        Fecha de nacimiento
                                    </label>

                                    <input
                                        id="birth_at"
                                        name="birth_at"
                                        type="date"
                                        max="{{ now()->format('Y-m-d') }}"
                                        value="{{ old(
                                            'birth_at',
                                            $user->birth_at?->format('Y-m-d')
                                        ) }}"
                                    >

                                    @error('birth_at', 'profileUpdate')
                                        <span class="profile-error">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>

                                <div class="profile-field">
                                    <label for="image">
                                        Imagen de perfil
                                    </label>

                                    <input
                                        id="image"
                                        name="image"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        data-avatar-input
                                    >

                                    <small>
                                        Selecciona una imagen y podrás ajustar su posición y zoom antes de guardarla.
                                    </small>

                                    <small>
                                        Máximo 2 MB y 1600 × 1600 píxeles.
                                    </small>

                                    @error('image', 'profileUpdate')
                                        <span class="profile-error">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="profile-form__actions">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    Guardar cambios
                                </button>
                            </div>
                        </form>
                    </section>

                    <section class="profile-card">
                        <header class="profile-card__header">
                            <span>Seguridad</span>
                            <h2>Cambiar contraseña</h2>

                            <p>
                                Confirma tu contraseña actual antes de establecer una nueva.
                            </p>
                        </header>

                        <form
                            method="POST"
                            action="{{ route(
                                'profile.password.update'
                            ) }}"
                            class="profile-form"
                        >
                            @csrf
                            @method('PUT')

                            <div class="profile-field">
                                <label for="current_password">
                                    Contraseña actual
                                </label>

                                <input
                                    id="current_password"
                                    name="current_password"
                                    type="password"
                                    autocomplete="current-password"
                                    required
                                >

                                @error(
                                    'current_password',
                                    'passwordUpdate'
                                )
                                    <span class="profile-error">
                                        {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            <div class="profile-form__columns">
                                <div class="profile-field">
                                    <label for="password">
                                        Nueva contraseña
                                    </label>

                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        autocomplete="new-password"
                                        required
                                    >

                                    @error(
                                        'password',
                                        'passwordUpdate'
                                    )
                                        <span class="profile-error">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>

                                <div class="profile-field">
                                    <label for="password_confirmation">
                                        Repetir contraseña
                                    </label>

                                    <input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="profile-form__actions">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    Actualizar contraseña
                                </button>
                            </div>
                        </form>
                    </section>

                </div>
            </div>
        </div>
    </main>
<div
    class="avatar-editor"
    data-avatar-editor
    hidden
>
    <div
        class="avatar-editor__backdrop"
        data-avatar-cancel
    ></div>

    <div
        class="avatar-editor__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="avatar-editor-title"
    >
        <header class="avatar-editor__header">
            <div>
                <span>Imagen de perfil</span>

                <h2 id="avatar-editor-title">
                    Ajustar imagen
                </h2>

                <p>
                    Arrastra la imagen para colocarla dentro del marco.
                </p>
            </div>

            <button
                type="button"
                class="avatar-editor__close"
                data-avatar-cancel
                aria-label="Cerrar"
            >
                ×
            </button>
        </header>

        <div class="avatar-editor__workspace">

            <div
                class="avatar-editor__viewport"
                data-avatar-viewport
            >
                <img
                    src=""
                    alt=""
                    class="avatar-editor__image"
                    data-avatar-image
                    draggable="false"
                >

                <div
                    class="avatar-editor__frame"
                    aria-hidden="true"
                ></div>
            </div>

            <p class="avatar-editor__hint">
                Arrastra la imagen con el ratón para cambiar el encuadre.
            </p>

        </div>

        <div class="avatar-editor__controls">

            <span class="avatar-editor__control-label">
                Zoom
            </span>

            <div class="avatar-editor__zoom">
                <button
                    type="button"
                    data-avatar-zoom-out
                    aria-label="Alejar"
                >
                    −
                </button>

                <input
                    type="range"
                    min="1"
                    max="3"
                    step="0.01"
                    value="1"
                    data-avatar-zoom
                >

                <button
                    type="button"
                    data-avatar-zoom-in
                    aria-label="Acercar"
                >
                    +
                </button>
            </div>

            <button
                type="button"
                class="avatar-editor__reset"
                data-avatar-reset
            >
                Restablecer encuadre
            </button>
        </div>

        <footer class="avatar-editor__footer">
            <button
                type="button"
                class="btn btn-outline"
                data-avatar-cancel
            >
                Cancelar
            </button>

            <button
                type="button"
                class="btn btn-primary"
                data-avatar-apply
            >
                Aplicar encuadre
            </button>
        </footer>
    </div>
</div>
<script
    src="{{ asset('js/landing.js') }}"
    defer
></script>
<script
    src="{{ asset('js/profile.js') }}"
    defer
></script>
</body>
</html>