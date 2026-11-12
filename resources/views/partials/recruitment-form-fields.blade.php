@php
    $recruitmentFields = \App\Support\RecruitmentFormFields::visible();
    $lastSection = null;
@endphp
@foreach($recruitmentFields as $field)
    @php
        $section = trim($field['section']);
        $fieldKey = $field['key'];
        $fieldType = $field['type'];
        $inputName = 'recruitment_answers[' . $fieldKey . ']';
        $oldValue = old('recruitment_answers.' . $fieldKey);
        $required = $field['required'];
        $maxLength = $fieldType === 'textarea' ? ($fieldKey === 'how_heard_us' ? 1500 : 4000) : (in_array($fieldKey, ['full_name', 'residence', 'discord_profile']) ? 160 : ($fieldKey === 'phone_whatsapp' ? 40 : 255));
    @endphp
    @if($section !== $lastSection)
        @if($lastSection !== null)
            </div>
        @endif
        <div class="requirement-block">
            @if($section !== '')<h3>{{ $section }}</h3>@endif
        @php $lastSection = $section; @endphp
    @endif

    @if($fieldType === 'checkbox')
        <input type="hidden" name="{{ $inputName }}" value="0">
        <label class="form-check" style="margin-bottom:10px;">
            <input type="checkbox" name="{{ $inputName }}" value="1" @checked((string) $oldValue === '1') @if($required) data-recruitment-required @endif>
            <span>
                {{ $field['label'] }}
                @if($field['url'])
                    <a href="{{ url($field['url']) }}" target="_blank" rel="noopener noreferrer">{{ $field['linkText'] ?: 'Ver información' }}</a>
                @endif
                @if($required)<small style="color:#e5a532;">Obligatorio</small>@endif
            </span>
        </label>
    @elseif($fieldType === 'textarea')
        <div class="form-field" style="margin-bottom:14px;">
            @include('partials.bbcode-editor', [
                'id' => 'recruitment-' . $fieldKey,
                'name' => $inputName,
                'label' => $field['label'] . ($required ? ' *' : ''),
                'value' => $oldValue,
                'rows' => 5,
                'maxlength' => $maxLength,
                'placeholder' => $field['placeholder'],
                'help' => 'Puedes dar formato a tu respuesta con las herramientas del editor.',
                'recruitmentRequired' => $required,
            ])
        </div>
    @else
        <label class="form-field {{ in_array($fieldType, ['text', 'email', 'tel']) ? 'form-field--full' : '' }}" style="margin-bottom:14px;">
            <span>{{ $field['label'] }} @if($required)*@endif</span>
            <input
                type="{{ $fieldType }}"
                name="{{ $inputName }}"
                value="{{ $oldValue }}"
                @if($fieldType !== 'date') maxlength="{{ $maxLength }}" @endif
                @if($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endif
                @if($required) data-recruitment-required @endif
                @if($fieldType === 'tel') inputmode="tel" @endif
            >
        </label>
    @endif
@endforeach
@if($lastSection !== null)
    </div>
@endif
