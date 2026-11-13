@extends('layouts.metopas')

@section('title', 'Sorteos de Squad ALPHA')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/community-raffles.css') }}?v={{ filemtime(public_path('css/community-raffles.css')) }}">
@endpush

@section('content')
<div class="community-shell sqa-raffle-page">
    <span class="sqa-raffle-kicker">COMUNIDAD · SQUAD ALPHA</span>
    <h1>Sorteos</h1>
    <p class="sqa-raffle-lead">Consulta los sorteos en los que participas y sus ganadores, también después de finalizar.</p>
    <div class="sqa-raffle-archive">
        @forelse($raffles as $raffle)
            <a class="sqa-raffle-archive-card" href="{{ route('raffles.show', $raffle) }}">
                <span>{{ $raffle->status === 'active' ? '● EN DIRECTO' : '✓ FINALIZADO' }}</span>
                <strong>{{ $raffle->title }}</strong>
                <small>{{ $raffle->description ? \Illuminate\Support\Str::limit($raffle->description, 150) : 'Consulta la ruleta y los premios.' }}</small>
                <em>Entrar →</em>
            </a>
        @empty
            <p class="sqa-raffle-empty">No tienes sorteos disponibles. Cuando te apunten a uno, aparecerá aquí.</p>
        @endforelse
    </div>
    {{ $raffles->links() }}
</div>
@endsection
