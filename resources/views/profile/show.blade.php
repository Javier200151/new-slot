@php
    $avatarUrl = $user->image
        ? asset('storage/' . $user->image)
        : asset('images/sqa-shield-white.png');
     
    $sqaGroups = $user->sqaGroups
        ->sortBy('display_order')
        ->values();

    $userNameColor = $user->getFrontendColor();    

    $statusMessage = match (session('status')) {
        'profile-updated' => 'Tu perfil se ha actualizado correctamente.',
        'profile-updated-email-changed' => 'Tu perfil se ha actualizado. Hemos enviado un enlace de verificación al nuevo correo.',
        'password-updated' => 'Tu contraseña se ha actualizado correctamente.',
        'image-deleted' => 'La imagen de perfil se ha eliminado.',
        'verification-link-sent' => 'Te hemos enviado un nuevo enlace de verificación.',
        'email-verified' => 'Tu correo electrónico se ha verificado correctamente.',
        'email-already-verified' => 'Tu correo electrónico ya estaba verificado.',
        'discord-linked' => 'Tu cuenta de Discord se ha vinculado y verificado correctamente.',
        'discord-unlinked' => 'La asociación con Discord se ha eliminado. No se han modificado roles ni accesos externos.',
        'steam-linked' => 'Tu cuenta de Steam se ha vinculado y verificado correctamente.',
        'steam-unlinked' => 'La asociación con Steam se ha eliminado.',
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
        href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/profile.css') }}"
    >
    <link rel="stylesheet" href="{{ asset('css/bbcode.css') }}?v={{ filemtime(public_path('css/bbcode.css')) }}">
</head>

<body class="landing-body">

    @include('partials.public-header')

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
                    <strong
                        @if($userNameColor)
                            style="color: {{ $userNameColor }};"
                        @endif
                    >
                        {{ $user->nick }}
                    </strong>
                </div>
            </header>

            @include('partials.profile-completion', [
                'profileCompletion' => $profileCompletion,
                'variant' => 'profile',
            ])

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

                        <h2
                            @if($userNameColor)
                                style="color: {{ $userNameColor }};"
                            @endif
                        >
                            {{ $user->nick }}
                        </h2>

                        @if($sqaGroups->isNotEmpty())
                            <div
                                class="profile-groups"
                                aria-label="Grupos SQA"
                            >
                                @foreach($sqaGroups as $group)

                                    @php
                                        $isMainGroup = (bool) $group->pivot?->main;
                                    @endphp

                                    <span
                                        class="profile-group-badge {{ $isMainGroup ? 'is-main' : '' }}"
                                        style="--group-color: {{ $group->color ?: '#f59e0b' }};"
                                        @if($isMainGroup)
                                            title="{{ $group->name }} · Grupo principal"
                                        @else
                                            title="{{ $group->name }}"
                                        @endif
                                    >
                                        @if($isMainGroup)
                                            <span
                                                class="profile-group-badge__star"
                                                aria-hidden="true"
                                            >
                                                ★
                                            </span>
                                        @endif

                                        {{ $group->name }}
                                    </span>

                                @endforeach
                            </div>
                        @endif

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
                                        ? '#' . $user->promo_id
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
                                    {{ $user->currentRecruitmentPeriod?->tutor?->nick
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

                    @if($treasuryPrivateVisible)
                    <section class="profile-card profile-treasury">
                        <header class="profile-card__header">
                            <span>Tesorería · Privado</span>
                            <h2>Mi saldo</h2>
                            <p>Esta información solo aparece en tu propio perfil.</p>
                        </header>

                        @if($treasuryMemberUnavailable)
                            <div class="profile-treasury__state profile-treasury__state--warning">
                                La información de Tesorería no está disponible ahora mismo.
                            </div>
                        @elseif(! ($treasuryMember['configured'] ?? false))
                            <div class="profile-treasury__state">
                                Tesorería todavía no está conectada.
                            </div>
                        @elseif(! ($treasuryMember['found'] ?? false))
                            <div class="profile-treasury__state">
                                No hemos encontrado una ficha de Tesorería asociada a tu nick actual.
                            </div>
                        @else
                            @php
                                $quarterPaid = (bool) ($treasuryMember['quarter_paid'] ?? false);
                                $quarterClass = $quarterPaid ? 'is-paid' : 'is-debt';
                                $quarterLabel = (string) ($treasuryMember['quarter_label'] ?? 'Trimestre');
                                $quarterPeriod = (string) ($treasuryMember['quarter_period'] ?? '');
                                $quarterPrice = (float) ($treasuryMember['quarter_price'] ?? 9);
                                $quarterMissing = (float) ($treasuryMember['quarter_missing'] ?? 0);
                                $quarterAvailable = (float) ($treasuryMember['quarter_available'] ?? 0);
                                $displayBalance = (float) ($treasuryMember['display_balance'] ?? 0);
                                $arrearsDue = (float) ($treasuryMember['arrears_due'] ?? 0);
                                $quarterDue = (float) ($treasuryMember['quarter_due'] ?? $quarterMissing);
                                $giftedMonths = (int) ($treasuryMember['gifted_months'] ?? 0);
                                $pendingCurrentMonth = (float) ($treasuryMember['pending_current_month'] ?? 0);
                                $balanceLabel = $quarterPaid
                                    ? 'Remanente tras cubrir el trimestre'
                                    : 'Disponible para completar el trimestre';
                                $quarterStatus = $quarterPaid
                                    ? 'PAGADO'
                                    : ($quarterAvailable <= 0.00001
                                        ? 'DEBE ' . number_format($quarterMissing, 2, ',', '.') . ' €'
                                        : 'Faltan ' . number_format($quarterMissing, 2, ',', '.') . ' €');
                                $lastPayment = $treasuryMember['last_payment'] ?? null;
                            @endphp

                            <div class="profile-treasury__balance">
                                <span>{{ $balanceLabel }}</span>
                                <strong>{{ number_format($displayBalance, 2, ',', '.') }} €</strong>
                            </div>

                            <dl class="profile-treasury__details">
                                <div>
                                    <dt>{{ $quarterLabel }}</dt>
                                    <dd class="{{ $quarterClass }}">
                                        {{ $quarterStatus }}
                                        <span>{{ $quarterPeriod }} · Cuota {{ number_format($quarterPrice, 2, ',', '.') }} €</span>
                                        @if($giftedMonths > 0)
                                            <span>{{ $giftedMonths === 1 ? '1 mes regalado (G)' : $giftedMonths . ' meses regalados (G)' }}</span>
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt>Último pago</dt>
                                    <dd>
                                        @if($lastPayment)
                                            <strong>{{ number_format((float) $lastPayment['amount'], 2, ',', '.') }} €</strong>
                                            <span>{{ $lastPayment['date'] }}</span>
                                        @else
                                            Sin pagos registrados
                                        @endif
                                    </dd>
                                </div>
                            </dl>

                            @if(! $quarterPaid && ($arrearsDue > 0.00001 || $pendingCurrentMonth > 0.00001))
                                <p class="profile-treasury__concept">
                                    @if($arrearsDue > 0.00001)
                                        Pendiente anterior o del mes en curso: <strong>{{ number_format($arrearsDue, 2, ',', '.') }} €</strong>.
                                    @endif
                                    @if($quarterDue > 0.00001)
                                        Para {{ $quarterLabel }}: <strong>{{ number_format($quarterDue, 2, ',', '.') }} €</strong>.
                                    @endif
                                </p>
                            @endif

                            @if($lastPayment && filled($lastPayment['concept'] ?? null))
                                <p class="profile-treasury__concept">
                                    {{ $lastPayment['concept'] }}
                                </p>
                            @endif
                        @endif
                    </section>
                    @endif

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
                                @include('partials.bbcode-editor', [
                                    'id' => 'quote',
                                    'name' => 'quote',
                                    'label' => 'Frase personal',
                                    'value' => old('quote', $user->quote),
                                    'rows' => 4,
                                    'maxlength' => 500,
                                    'required' => false,
                                ])

                                @error('quote', 'profileUpdate')
                                    <span class="profile-error">
                                        {{ $message }}
                                    </span>
                                @enderror
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

                    @include('profile.linked-accounts')

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
<script src="{{ asset('js/bbcode-editor.js') }}?v={{ filemtime(public_path('js/bbcode-editor.js')) }}" defer></script>
</body>
</html>