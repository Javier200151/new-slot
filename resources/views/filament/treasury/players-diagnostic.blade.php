<div style="display:grid;gap:1rem;">
    @if($error)
        <div style="padding:1rem;border:1px solid rgba(239,68,68,.35);border-radius:.75rem;background:rgba(239,68,68,.08);">
            <strong style="display:block;color:#fca5a5;margin-bottom:.35rem;">No se pudo ejecutar el diagnóstico</strong>
            <span>{{ $error }}</span>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;">
            <div style="padding:.85rem 1rem;border:1px solid rgba(148,163,184,.25);border-radius:.75rem;">
                <span style="display:block;font-size:.75rem;color:#94a3b8;">Filas comprobadas</span>
                <strong style="font-size:1.3rem;">{{ $report['total'] }}</strong>
            </div>
            <div style="padding:.85rem 1rem;border:1px solid rgba(34,197,94,.3);border-radius:.75rem;background:rgba(34,197,94,.06);">
                <span style="display:block;font-size:.75rem;color:#94a3b8;">Correctas</span>
                <strong style="font-size:1.3rem;color:#86efac;">{{ $report['ok'] }}</strong>
            </div>
            <div style="padding:.85rem 1rem;border:1px solid rgba(239,68,68,.3);border-radius:.75rem;background:rgba(239,68,68,.06);">
                <span style="display:block;font-size:.75rem;color:#94a3b8;">A revisar</span>
                <strong style="font-size:1.3rem;color:#fca5a5;">{{ $report['errors'] }}</strong>
            </div>
        </div>

        @if($report['errors'] === 0)
            <div style="padding:1rem;border:1px solid rgba(34,197,94,.3);border-radius:.75rem;background:rgba(34,197,94,.06);color:#bbf7d0;">
                Todos los nicknames de la pestaña <strong>Jugadores</strong> existen en la web y sus estados coinciden.
            </div>
        @else
            <div style="overflow:auto;border:1px solid rgba(148,163,184,.22);border-radius:.75rem;">
                <table style="width:100%;border-collapse:collapse;min-width:760px;">
                    <thead>
                        <tr style="background:rgba(148,163,184,.08);text-align:left;">
                            <th style="padding:.7rem;">Fila</th>
                            <th style="padding:.7rem;">Nickname</th>
                            <th style="padding:.7rem;">Tesorería</th>
                            <th style="padding:.7rem;">Web</th>
                            <th style="padding:.7rem;">Debería ser</th>
                            <th style="padding:.7rem;">Problema</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['issues'] as $issue)
                            <tr style="border-top:1px solid rgba(148,163,184,.16);vertical-align:top;">
                                <td style="padding:.7rem;white-space:nowrap;">{{ $issue['row'] ?? '—' }}</td>
                                <td style="padding:.7rem;font-weight:700;">{{ $issue['nick'] }}</td>
                                <td style="padding:.7rem;">{{ $issue['treasury_state'] ?: '—' }}</td>
                                <td style="padding:.7rem;">{{ $issue['web_state'] ?: '—' }}</td>
                                <td style="padding:.7rem;">{{ $issue['expected_state'] ?: '—' }}</td>
                                <td style="padding:.7rem;color:#fca5a5;">{{ $issue['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p style="margin:0;color:#94a3b8;font-size:.82rem;">
            Equivalencias: ACTIVO → Miembro · RECLUTA → Recluta · RESERVA → Reserva · cualquier otro estado → Cesado.
            La asociación se realiza únicamente por nickname.
        </p>
    @endif
</div>
