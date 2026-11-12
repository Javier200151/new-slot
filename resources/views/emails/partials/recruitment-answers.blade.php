@if($submission->is_recruitment && $submission->recruitment_answers !== null)
    <tr>
        <td style="padding:18px 36px 28px;">
            <div style="margin-bottom:14px;color:#f59e0b;font-size:12px;font-weight:800;">Respuestas del alistamiento</div>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                @foreach($submission->recruitment_answers as $answer)
                    @if(is_array($answer))
                        <tr>
                            <td style="padding:11px 12px;width:42%;border-bottom:1px solid #252b35;vertical-align:top;color:#9ca3af;font-size:13px;">
                                {{ $answer['label'] ?? 'Pregunta' }}
                            </td>
                            <td style="padding:11px 12px;border-bottom:1px solid #252b35;vertical-align:top;color:#f7f7f8;font-size:13px;overflow-wrap:anywhere;">
                                @if(($answer['type'] ?? '') === 'checkbox')
                                    {{ ! empty($answer['value']) ? 'Sí' : 'No' }}
                                @else
                                    {!! nl2br(e((string) ($answer['value'] ?? ''))) !!}
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
                @foreach([
                    'Política de privacidad aceptada' => $submission->accepted_privacy,
                    'Consentimiento de contacto' => $submission->accepted_contact,
                ] as $label => $checked)
                    <tr>
                        <td style="padding:11px 12px;border-bottom:1px solid #252b35;vertical-align:top;color:#9ca3af;font-size:13px;">{{ $label }}</td>
                        <td style="padding:11px 12px;border-bottom:1px solid #252b35;color:#f7f7f8;font-size:13px;">{{ $checked ? 'Sí' : 'No' }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
@endif
