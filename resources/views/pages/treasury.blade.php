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
                <div class="treasury-payment-entry">
                    <button type="button" class="treasury-payment-trigger" data-treasury-payment-open aria-haspopup="dialog" aria-controls="treasury-payment-dialog">
                        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <path d="M2 10h20" />
                        </svg>
                        Medios de pago
                    </button>
                </div>
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
                            <p>Datos asociados únicamente a tu nickname.</p>
                        </header>

                        @if($treasuryMemberUnavailable)
                            <div class="treasury-state treasury-state--compact treasury-private__state">
                                La información privada de Tesorería no está disponible ahora mismo.
                            </div>
                        @elseif(! ($treasuryMember['configured'] ?? false))
                            <div class="treasury-state treasury-state--compact treasury-private__state">
                                Tesorería todavía no está conectada.
                            </div>
                        @elseif(! ($treasuryMember['found'] ?? false))
                            <div class="treasury-state treasury-state--compact treasury-private__state">
                                No se ha encontrado una ficha de Tesorería con tu nickname actual: <strong>{{ auth()->user()->nick }}</strong>.
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

                            <div class="treasury-private__grid">
                                <article class="treasury-private__item treasury-private__item--balance">
                                    <span>{{ $balanceLabel }}</span>
                                    <strong>{{ number_format($displayBalance, 2, ',', '.') }} €</strong>
                                </article>

                                <article class="treasury-private__item">
                                    <span>{{ $quarterLabel }}</span>
                                    <strong class="{{ $quarterClass }}">{{ $quarterStatus }}</strong>
                                    <small>{{ $quarterPeriod }} · Cuota {{ number_format($quarterPrice, 2, ',', '.') }} €</small>
                                    @if($giftedMonths > 0)
                                        <small>{{ $giftedMonths === 1 ? '1 mes regalado (G)' : $giftedMonths . ' meses regalados (G)' }}</small>
                                    @endif
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

                            @if(! $quarterPaid && ($arrearsDue > 0.00001 || $pendingCurrentMonth > 0.00001))
                                <p class="treasury-private__concept">
                                    @if($arrearsDue > 0.00001)
                                        Pendiente anterior o del mes en curso: <strong>{{ number_format($arrearsDue, 2, ',', '.') }} €</strong>.
                                    @endif
                                    @if($quarterDue > 0.00001)
                                        Para {{ $quarterLabel }}: <strong>{{ number_format($quarterDue, 2, ',', '.') }} €</strong>.
                                    @endif
                                </p>
                            @endif

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

    <dialog id="treasury-payment-dialog" class="treasury-payment-dialog" aria-labelledby="treasury-payment-title" aria-describedby="treasury-payment-description">
        <div class="treasury-payment-dialog__header">
            <div>
                <span>TESORERÍA · SQUAD ALPHA</span>
                <h2 id="treasury-payment-title">Medios de pago</h2>
            </div>
            <button type="button" class="treasury-payment-dialog__close" data-treasury-payment-close aria-label="Cerrar ventana">&times;</button>
        </div>
        <p id="treasury-payment-description" class="treasury-payment-dialog__intro">Elige cómo quieres realizar tu aportación a Squad ALPHA.</p>

        @if($treasuryPaymentMethods['iban'])
            <section class="treasury-payment-method">
                <div class="treasury-payment-method__label">Transferencia bancaria</div>
                <p>IBAN</p>
                <div class="treasury-payment-method__iban">{{ implode(' ', str_split($treasuryPaymentMethods['iban'], 4)) }}</div>
                <button type="button" class="treasury-payment-method__copy" data-treasury-copy-iban data-iban="{{ $treasuryPaymentMethods['iban'] }}">Copiar IBAN</button>
                @if(filled($treasuryPaymentMethods['beneficiary'] ?? null))
                    <div class="treasury-payment-method__beneficiary">
                        <p>Destinatario de la transferencia</p>
                        <strong>{{ $treasuryPaymentMethods['beneficiary'] }}</strong>
                        <small>Escribe este nombre como destinatario al hacer la transferencia.</small>
                        <button type="button" class="treasury-payment-method__copy treasury-payment-method__copy--beneficiary" data-treasury-copy-beneficiary data-beneficiary="{{ $treasuryPaymentMethods['beneficiary'] }}">Copiar destinatario</button>
                    </div>
                @endif
            </section>
        @endif

        @if($treasuryPaymentMethods['paypal_url'])
            <section class="treasury-payment-method">
                <div class="treasury-payment-method__label">PayPal</div>
                <p>Puedes realizar el pago desde PayPal.</p>
                <a class="treasury-payment-method__paypal" href="{{ $treasuryPaymentMethods['paypal_url'] }}" target="_blank" rel="noopener noreferrer">Pagar con PayPal <span aria-hidden="true">↗</span></a>
            </section>
        @endif

        @if(! $treasuryPaymentMethods['iban'] && ! $treasuryPaymentMethods['paypal_url'])
            <p class="treasury-payment-dialog__empty">Los medios de pago todavía no están configurados.</p>
        @endif
        <p class="treasury-payment-dialog__feedback" data-treasury-payment-feedback role="status" aria-live="polite"></p>
    </dialog>
@endsection

@push('scripts')
    <script src="{{ asset('js/treasury-payments.js') }}?v={{ filemtime(public_path('js/treasury-payments.js')) }}" defer></script>
@endpush
