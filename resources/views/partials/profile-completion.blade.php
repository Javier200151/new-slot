@if($profileCompletion && ! $profileCompletion['is_complete'])
    @php
        $missingCount = $profileCompletion['missing_count'];
        $stepsLabel = $missingCount === 1 ? 'paso' : 'pasos';
        $targetAnchor = $profileCompletion['missing'][0]['anchor'] ?? 'profile-linked-accounts';
        $targetUrl = ($variant ?? 'profile') === 'home'
            ? route('profile.show') . '#' . $targetAnchor
            : '#' . $targetAnchor;
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
            Revisa los pasos pendientes para completar la información y preferencias necesarias de tu perfil.
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
            <div class="profile-completion__steps" aria-label="Estado de los datos del perfil">
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
