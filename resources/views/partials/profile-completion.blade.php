@if($profileCompletion && ! $profileCompletion['is_complete'])
    @php
        $missingCount = $profileCompletion['missing_count'];
        $stepsLabel = $missingCount === 1 ? 'paso' : 'pasos';
        $targetUrl = ($variant ?? 'profile') === 'home'
            ? route('profile.show') . '#profile-linked-accounts'
            : '#profile-linked-accounts';
    @endphp

    <section class="profile-completion profile-completion--{{ $variant ?? 'profile' }}" aria-label="Progreso de perfil">
        <div class="profile-completion__topline">
            <div>
                <span class="profile-completion__eyebrow">Completa tu perfil</span>
                <strong>
                    Te {{ $missingCount === 1 ? 'falta' : 'faltan' }}
                    {{ $missingCount }} {{ $stepsLabel }} para completar tu perfil
                </strong>
            </div>

            <span class="profile-completion__percentage">
                {{ $profileCompletion['percent'] }}%
            </span>
        </div>

        <p>
            Vincula Discord y Steam para obtener automáticamente los identificadores usados por
            Discord, ArmaSquads y los procedimientos de miembro.
        </p>

        <div
            class="profile-completion__progress"
            role="progressbar"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $profileCompletion['percent'] }}"
        >
            <span style="width: {{ $profileCompletion['percent'] }}%;"></span>
        </div>

        <div class="profile-completion__footer">
            <div class="profile-completion__steps" aria-label="Estado de cuentas vinculadas">
                @foreach($profileCompletion['steps'] as $step)
                    <span class="{{ $step['complete'] ? 'is-complete' : 'is-pending' }}">
                        <b aria-hidden="true">{{ $step['complete'] ? '✓' : '○' }}</b>
                        {{ $step['description'] }}
                    </span>
                @endforeach
            </div>

            <a href="{{ $targetUrl }}" class="profile-completion__action">
                Completar ahora
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </section>
@endif
