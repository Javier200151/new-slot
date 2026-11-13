@extends('layouts.metopas')

@section('title', $raffle->title . ' · Sorteo Squad ALPHA')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/community-raffles.css') }}?v={{ filemtime(public_path('css/community-raffles.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/community-raffles.js') }}?v={{ filemtime(public_path('js/community-raffles.js')) }}" defer></script>
@endpush

@section('content')
<div class="community-shell sqa-raffle-page" data-community-raffle
    data-state-url="{{ route('raffles.state', $raffle) }}"
    data-viewer-id="{{ auth()->id() ?? '' }}"
    @if($canManage && in_array($raffle->status, ['active', 'closed'], true))
        data-spin-url="{{ route('raffles.spin', $raffle) }}"
        data-reroll-base="{{ url('/sorteos/'.$raffle->id.'/repetir') }}"
        data-change-prize-base="{{ url('/sorteos/'.$raffle->id.'/cambiar-premio') }}"
        data-csrf="{{ csrf_token() }}"
    @endif
>
    <div class="sqa-raffle-topbar">
        <a class="sqa-raffle-back" href="{{ route('raffles.index') }}">← Todos los sorteos</a>
        <div class="sqa-raffle-live-state">
            <span class="sqa-raffle-live-dot{{ $raffle->status === 'active' ? ' is-live' : '' }}"></span>
            <strong data-raffle-status>{{ $raffle->status === 'active' ? 'En directo' : ($raffle->status === 'closed' ? 'Finalizado' : 'En preparación') }}</strong>
        </div>
    </div>

    @if(session('raffle_status'))<div class="sqa-raffle-notice">{{ session('raffle_status') }}</div>@endif
    @if($errors->any())<div class="sqa-raffle-error">{{ $errors->first() }}</div>@endif

    <header class="sqa-raffle-hero">
        <div>
            <span class="sqa-raffle-kicker">{{ $raffle->status === 'active' ? '● SORTEO EN DIRECTO' : ($raffle->status === 'closed' ? 'SORTEO FINALIZADO' : 'EN PREPARACIÓN') }}</span>
            <h1>{{ $raffle->title }}</h1>
            @if($raffle->description)<p>{{ $raffle->description }}</p>@endif
        </div>
        <div class="sqa-raffle-hero__meta">
            <span><b>{{ count($state['prizes']) }}</b> premio(s)</span>
            <span><b data-raffle-participants-count>{{ $state['participants_count'] }}</b> participantes</span>
            @if($raffle->status === 'closed')
                <span><b>{{ count($state['winners']) }}</b> ganador(es)</span>
            @endif
        </div>
    </header>

    <div class="sqa-raffle-main-grid">
        <section class="sqa-raffle-stage">
            <div class="sqa-raffle-stage__halo"></div>
            <div class="sqa-raffle-wheel-wrap" data-raffle-wheel-box>
                <span class="sqa-raffle-pointer" aria-hidden="true"></span>
                <canvas width="660" height="660" aria-label="Ruleta con los participantes del sorteo" data-raffle-wheel></canvas>
                <div class="sqa-raffle-wheel-center">SQA</div>
            </div>
            <div class="sqa-raffle-result" data-raffle-result aria-live="polite">
                <strong data-raffle-result-title>{{ $raffle->status === 'closed' ? 'Sorteo finalizado' : 'Preparados para el sorteo' }}</strong>
                <p data-raffle-result-message>{{ $raffle->status === 'closed' ? 'Consulta los ganadores y cómo reclamar los premios.' : 'Los participantes aparecerán en la ruleta al ser seleccionados.' }}</p>
            </div>
            @if($canManage && $raffle->status === 'active')
                <button type="button" class="sqa-raffle-spin-button" data-raffle-spin>
                    <span>Girar ruleta</span>
                    <small>Escoge antes el premio que quieres sortear</small>
                </button>
            @endif
            @if($canManage && $raffle->status === 'active')
                <p class="sqa-raffle-error sqa-raffle-spin-error" data-raffle-spin-error role="alert" hidden></p>
            @endif
            <div class="sqa-raffle-live-note"><span class="sqa-raffle-live-dot is-live"></span> <span data-raffle-participants-count>{{ $state['participants_count'] }}</span> participantes seleccionados · La ruleta se actualiza en directo</div>
        </section>

        <aside class="sqa-raffle-prize-panel">
            <div class="sqa-raffle-panel-head">
                <div>
                    <span class="sqa-raffle-panel-kicker">Premios</span>
                    <h2>Premios disponibles</h2>
                </div>
                <span>{{ count($state['prizes']) }} en total</span>
            </div>

            <div class="sqa-raffle-prize-list" data-raffle-prizes>
                @foreach($state['prizes'] as $prize)
                    <article class="sqa-raffle-prize">
                        @if($prize['image_url'])<img src="{{ $prize['image_url'] }}" alt="{{ $prize['name'] }}" loading="lazy">@endif
                        <div>
                            <strong>{{ $prize['name'] }}</strong>
                            <small>{{ $prize['remaining'] }} de {{ $prize['quantity'] }} disponibles</small>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($canManage && $raffle->status === 'active')
                <div class="sqa-raffle-manage-spin">
                    <label for="raffle-prize-select">¿Qué premio quieres sortear?</label>
                    <select id="raffle-prize-select" data-raffle-prize-select>
                        @foreach($state['prizes'] as $prize)
                            <option value="{{ $prize['id'] }}" @disabled($prize['remaining'] < 1)>{{ $prize['name'] }} · {{ $prize['remaining'] }} restantes</option>
                        @endforeach
                    </select>
                    <small>Los ganadores no vuelven a entrar en otra tirada de este sorteo. Puedes repetir una tirada o cambiar el premio de un ganador desde el historial inferior.</small>
                </div>
            @endif
        </aside>
    </div>

    <section class="sqa-raffle-winners">
        <div class="sqa-raffle-section-head">
            <div>
                <span class="sqa-raffle-panel-kicker">Historial</span>
                <h2>Ganadores y premios</h2>
            </div>
        </div>
        <div data-raffle-winners class="sqa-raffle-winners__grid"></div>
        @if($raffle->claim_message)
            <div class="sqa-raffle-claims">
                <strong>Cómo reclamar tu premio</strong>
                <p>{{ $raffle->claim_message }}</p>
            </div>
        @endif
    </section>

    @if($canManage && $raffle->status === 'active')
        <details class="sqa-raffle-manager" id="participantes">
            <summary>⚙ Gestionar participantes <small>Seleccionar por estado o individualmente</small></summary>
            <div class="sqa-raffle-manager__body" data-raffle-manager>
                <form method="POST" action="{{ route('raffles.participants', $raffle) }}" data-raffle-participants-form>
                    @csrf
                    <div class="sqa-raffle-manager__selectors">
                        @foreach($statuses as $status)
                            <button type="button" data-raffle-select-status="{{ $status->id }}">Añadir {{ $status->name }}</button>
                        @endforeach
                        <button type="button" data-raffle-clear>Quitar todos</button>
                    </div>
                    <div class="sqa-raffle-manager__searches">
                        <label>Buscar miembros
                            <input type="search" placeholder="Buscar por nick..." data-raffle-search-all>
                        </label>
                        <label>Buscar entre seleccionados
                            <input type="search" placeholder="Buscar para quitar..." data-raffle-search-selected>
                        </label>
                    </div>
                    <p><strong data-raffle-selected-count>{{ count($selectedIds) }}</strong> participantes seleccionados. Puedes añadir o quitar cualquier usuario manualmente aunque no pertenezca a los estados elegidos.</p>
                    <div class="sqa-raffle-manager__roster">
                        @foreach($managerUsers as $member)
                            <label data-raffle-person data-name="{{ mb_strtolower($member->nick) }}" data-status="{{ $member->status_id }}">
                                <input type="checkbox" name="user_ids[]" value="{{ $member->id }}" @checked(in_array($member->id, $selectedIds, true))>
                                <span>{{ $member->nick }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button class="sqa-raffle-action" type="submit">Guardar participantes</button>
                </form>
            </div>
        </details>

        <section class="sqa-raffle-admin-actions">
            <div>
                <h3>Historial de tiradas</h3>
                <p>Puedes repetir la ruleta directamente o cambiar el premio de un ganador, también después de cerrar el sorteo.</p>
                <p>Cada cambio queda registrado y los ganadores afectados reciben una notificación.</p>
            </div>
            <form method="POST" action="{{ route('raffles.close', $raffle) }}" data-sqa-confirm="¿Cerrar el sorteo? Ya no se podrán realizar más tiradas nuevas, aunque sí podrás repetir una ya realizada o cambiar el premio de un ganador.">
                @csrf
                <button type="submit" class="sqa-raffle-action sqa-raffle-action--secondary">Cerrar sorteo y publicar ganadores</button>
            </form>
        </section>
    @endif

    @if($canManage && $raffle->status === 'closed')
        <div class="sqa-raffle-notice">El sorteo está cerrado. Puedes repetir una tirada o cambiar el premio desde las tarjetas de ganadores. Los cambios se publican automáticamente.</div>
    @endif
</div>
<script type="application/json" id="sqa-raffle-initial-{{ $raffle->id }}" data-raffle-initial>@json($state)</script>
@endsection
