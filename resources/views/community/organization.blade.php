@extends('layouts.metopas')

@section('title', 'Organigrama')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}">
@endpush

@section('content')
<div class="community-shell">
    <span class="community-kicker">Comunidad</span>
    <h1 class="community-title">Organigrama</h1>
    <p class="community-lead">
        Mapa de los grupos de Squad Alpha y sus integrantes actuales.
    </p>

    <div class="org-grid">
        @forelse($groups as $group)
            <section
                class="org-group"
                style="--group-color: {{ $group->color ?: '#f59e0b' }}"
            >
                <header class="org-group__head">
                    @if($group->icon)
                        <img
                            src="{{ asset('storage/' . $group->icon) }}"
                            alt="Icono de {{ $group->large_name ?: $group->name }}"
                            class="org-group__image"
                        >
                    @else
                        <div class="org-group__image org-group__image--empty" aria-hidden="true">
                            {{ mb_strtoupper(mb_substr($group->name, 0, 2)) }}
                        </div>
                    @endif
                    <div class="org-group__identity">
                        <h2>
                            <span class="org-group__abbr">{{ $group->name }}</span>
                            @if(filled($group->large_name))
                                <span class="org-group__separator" aria-hidden="true">·</span>
                                <span class="org-group__long-name">{{ $group->large_name }}</span>
                            @endif
                        </h2>
                        @if($group->description)
                            <p>{{ $group->description }}</p>
                        @endif
                    </div>
                </header>

                @php
                    $hasCoordinatorRole = (bool) $group->has_coordinator_role;
                    $coordinator = $hasCoordinatorRole
                        ? $group->users->first(fn ($member) => (bool) ($member->pivot?->coordinator ?? false))
                        : null;
                    $members = $hasCoordinatorRole && $coordinator
                        ? $group->users->reject(fn ($member) => $member->id === $coordinator->id)
                        : $group->users;
                @endphp

                <div class="org-members">
                    @if($hasCoordinatorRole)
                        <div @class(['org-member', 'org-member--coordinator', 'is-empty' => ! $coordinator])>
                            <div class="org-member__main">
                                <span class="org-member__role">Coordinador</span>
                                @if($coordinator)
                                    <a
                                        href="{{ route('users.show', ['user' => $coordinator->nick]) }}"
                                        style="--member-color: {{ $coordinator->getFrontendColor() }}"
                                    >
                                        {{ $coordinator->nick }}
                                    </a>
                                @else
                                    <span class="org-member__placeholder">Sin asignar</span>
                                @endif
                            </div>
                            <small>{{ $coordinator?->status?->name ?? 'Vacante' }}</small>
                        </div>
                    @endif

                    @foreach($members as $member)
                        <div class="org-member">
                            <a
                                href="{{ route('users.show', ['user' => $member->nick]) }}"
                                style="--member-color: {{ $member->getFrontendColor() }}"
                            >
                                {{ $member->nick }}
                            </a>
                            <small>{{ $member->status?->name }}</small>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="community-empty">Todavía no hay grupos configurados.</div>
        @endforelse
    </div>
</div>
@endsection
