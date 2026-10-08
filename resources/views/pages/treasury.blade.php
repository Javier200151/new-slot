@extends('layouts.metopas')

@section('title', $page->title)

@section('meta-description', 'Resumen público de Tesorería de Squad ALPHA.')

@section('body-class', 'public-page-body treasury-page-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages.css') }}?v={{ filemtime(public_path('css/pages.css')) }}">
@endpush

@section('content')
    <article class="public-page treasury-page">
        <div class="container public-page__container treasury-page__container">
            <nav class="public-page__breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('home') }}">Inicio</a>
                <span aria-hidden="true">/</span>
                <span>{{ $page->title }}</span>
            </nav>

            <header class="public-page__header treasury-page__header">
                <span>Transparencia</span>
                <h1>{{ $page->title }}</h1>
                <p>
                    Resumen público de las cuentas de Squad ALPHA. Los saldos y pagos individuales
                    permanecen privados y solo los consulta cada miembro desde Mi perfil.
                </p>
            </header>

            @if(filled((string) $page->content))
                <section class="public-page__content treasury-page__intro">
                    {{ $content }}
                </section>
            @endif

            @if($treasuryUnavailable)
                <section class="treasury-state treasury-state--error">
                    <strong>La información de Tesorería no está disponible temporalmente.</strong>
                    <span>Vuelve a intentarlo dentro de unos minutos.</span>
                </section>
            @elseif(! ($treasury['configured'] ?? false))
                <section class="treasury-state">
                    <strong>Tesorería todavía no está conectada.</strong>
                    <span>La página quedará disponible automáticamente cuando se configure la hoja privada.</span>
                </section>
            @else
                <section class="treasury-summary" aria-label="Resumen de Tesorería">
                    <article class="treasury-kpi treasury-kpi--bank">
                        <span>Banco</span>
                        <strong>{{ $treasury['bank'] !== null ? number_format((float) $treasury['bank'], 2, ',', '.') . ' €' : '—' }}</strong>
                        <small>Saldo actual</small>
                    </article>

                    <article class="treasury-kpi treasury-kpi--paypal">
                        <span>PayPal</span>
                        <strong>{{ $treasury['paypal'] !== null ? number_format((float) $treasury['paypal'], 2, ',', '.') . ' €' : '—' }}</strong>
                        <small>Saldo actual</small>
                    </article>

                    <article class="treasury-kpi treasury-kpi--total">
                        <span>Tesorería total</span>
                        <strong>{{ $treasury['total'] !== null ? number_format((float) $treasury['total'], 2, ',', '.') . ' €' : '—' }}</strong>
                        <small>Banco + PayPal</small>
                    </article>
                </section>

                @if($treasuryPrivateVisible)
                    <section class="treasury-private" aria-label="Mi Tesorería privada">
                        <header class="treasury-private__header">
                            <div>
                                <span>Privado · Solo tú</span>
                                <h2>Mi Tesorería</h2>
                            </div>
                            <p>Datos asociados únicamente a tu nickname de NewSlot.</p>
                        </header>

                        @if($treasuryMemberUnavailable)
                            <div class="treasury-state treasury-state--compact treasury-private__state">
                                La información privada de Tesorería no está disponible ahora mismo.
                            </div>
                        @elseif(! ($treasuryMember['configured'] ?? false))
                            <div class="treasury-state treasury-state--compact treasury-private__state">
                                Tesorería todavía no está conectada con NewSlot.
                            </div>
                        @elseif(! ($treasuryMember['found'] ?? false))
                            <div class="treasury-state treasury-state--compact treasury-private__state">
                                No se ha encontrado una ficha de Tesorería con tu nickname actual: <strong>{{ auth()->user()->nick }}</strong>.
                            </div>
                        @else
                            @php
                                $quarterText = trim((string) ($treasuryMember['next_quarter'] ?? ''));
                                $quarterNormalized = mb_strtoupper($quarterText);
                                $quarterClass = str_contains($quarterNormalized, 'DEBE')
                                    ? 'is-debt'
                                    : (str_contains($quarterNormalized, 'PAGADO') ? 'is-paid' : '');
                                $lastPayment = $treasuryMember['last_payment'] ?? null;
                            @endphp

                            <div class="treasury-private__grid">
                                <article class="treasury-private__item treasury-private__item--balance">
                                    <span>Remanente actual</span>
                                    <strong>{{ $treasuryMember['remanent'] !== null ? number_format((float) $treasuryMember['remanent'], 2, ',', '.') . ' €' : '—' }}</strong>
                                </article>

                                <article class="treasury-private__item">
                                    <span>Próximo trimestre</span>
                                    <strong class="{{ $quarterClass }}">{{ $quarterText !== '' ? $quarterText : 'Sin dato' }}</strong>
                                </article>

                                <article class="treasury-private__item">
                                    <span>Último pago</span>
                                    @if($lastPayment)
                                        <strong>{{ number_format((float) $lastPayment['amount'], 2, ',', '.') }} €</strong>
                                        <small>{{ $lastPayment['date'] }}</small>
                                    @else
                                        <strong>—</strong>
                                        <small>Sin pagos registrados</small>
                                    @endif
                                </article>
                            </div>

                            @if($lastPayment && filled($lastPayment['concept'] ?? null))
                                <p class="treasury-private__concept">{{ $lastPayment['concept'] }}</p>
                            @endif
                        @endif
                    </section>
                @endif

                <section class="treasury-expenses">
                    <header class="treasury-section-heading">
                        <div>
                            <span>Movimientos públicos</span>
                            <h2>Gastos de los últimos 12 meses</h2>
                        </div>
                        <p>
                            Solo se muestran movimientos registrados como <strong>Gasto</strong>.
                            No se publican pagos, remanentes, deudas ni nombres de miembros.
                        </p>
                    </header>

                    @if(($treasury['expenses'] ?? []) === [])
                        <div class="treasury-state treasury-state--compact">
                            No hay gastos registrados desde {{ $treasury['from'] ?? '' }}.
                        </div>
                    @else
                        <div class="treasury-table-wrap">
                            <table class="treasury-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Categoría</th>
                                        <th>Cuenta</th>
                                        <th class="is-number">Importe</th>
                                        <th>Concepto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($treasury['expenses'] as $expense)
                                        <tr>
                                            <td data-label="Fecha">{{ $expense['date'] }}</td>
                                            <td data-label="Categoría">
                                                <span class="treasury-category">{{ $expense['category'] }}</span>
                                            </td>
                                            <td data-label="Cuenta">{{ $expense['account'] }}</td>
                                            <td data-label="Importe" class="is-number is-amount">
                                                {{ number_format((float) $expense['amount'], 2, ',', '.') }} €
                                            </td>
                                            <td data-label="Concepto" class="treasury-concept">{{ $expense['concept'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <p class="treasury-updated">
                        Datos sincronizados automáticamente con la hoja de Tesorería.
                    </p>
                </section>
            @endif
        </div>
    </article>
@endsection
