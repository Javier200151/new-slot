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
                    @if($group->image)
                        <img
                            src="{{ asset('storage/' . $group->image) }}"
                            alt=""
                            class="org-group__image"
                        >
                    @else
                        <div class="org-group__image"></div>
                    @endif
                    <div>
                        <h2>{{ $group->large_name ?: $group->name }}</h2>
                        @if($group->description)
                            <p>{{ $group->description }}</p>
                        @endif
                    </div>
                </header>

                <div class="org-members">
                    @forelse($group->users as $member)
                        <div class="org-member">
                            <a
                                href="{{ route('users.show', ['user' => $member->nick]) }}"
                                style="--member-color: {{ $member->getFrontendColor() }}"
                            >
                                {{ $member->nick }}
                            </a>
                            <small>{{ $member->status?->name }}</small>
                        </div>
                    @empty
                        <div class="community-empty">Sin integrantes asignados.</div>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="community-empty">Todavía no hay grupos configurados.</div>
        @endforelse
    </div>
</div>
@endsection
