@if($profileCompletion && ! $profileCompletion['is_complete'])
    @php
        $missingCount = $profileCompletion['missing_count'];
        $stepsLabel = $missingCount === 1 ? 'paso' : 'pasos';
        $targetUrl = ($variant ?? 'profile') === 'home'
            ? route('profile.show') . '#profile-operational-data'
            : '#profile-operational-data';
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
            Añade tus identificadores para que NewSlot pueda automatizar Discord,
            ArmaSquads y los procedimientos de miembro.
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
            <div class="profile-completion__missing" aria-label="Datos pendientes">
                @foreach($profileCompletion['missing'] as $step)
                    <span>{{ $step['label'] }}</span>
                @endforeach
            </div>

            <a href="{{ $targetUrl }}" class="profile-completion__action">
                Completar ahora
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </section>
@endif
