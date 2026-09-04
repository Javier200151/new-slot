@php
    $reactionOptions = \App\Models\CommunityReaction::options();
    $reactionCollection = collect($reactable->reactions ?? []);
    $myReaction = $reactionCollection->firstWhere('user_id', auth()->id())?->reaction;
@endphp

<div class="forum-reactions" data-forum-reactions>
    <div class="forum-reactions__summary" aria-label="Reacciones">
        @foreach($reactionOptions as $reactionCode => $reactionOption)
            @php
                $reactionUsers = $reactionCollection
                    ->where('reaction', $reactionCode)
                    ->pluck('user.nick')
                    ->filter()
                    ->values();
                $reactionCount = $reactionUsers->count();
                $reactionTitle = $reactionCount
                    ? $reactionOption['label'] . ': ' . $reactionUsers->implode(', ')
                    : $reactionOption['label'];
            @endphp

            <form
                method="POST"
                action="{{ $reactionRoute }}"
                data-forum-reaction-form
                data-reaction-code="{{ $reactionCode }}"
                class="forum-reaction-form"
            >
                @csrf
                <input type="hidden" name="reaction" value="{{ $reactionCode }}">
                <button
                    type="submit"
                    class="forum-reaction-chip {{ $myReaction === $reactionCode ? 'is-active' : '' }}"
                    data-forum-reaction-chip
                    data-reaction-label="{{ $reactionOption['label'] }}"
                    title="{{ $reactionTitle }}"
                    @if($reactionCount === 0) hidden @endif
                >
                    <span aria-hidden="true">{{ $reactionOption['emoji'] }}</span>
                    <span data-forum-reaction-count>{{ $reactionCount }}</span>
                </button>
            </form>
        @endforeach
    </div>

    <details class="forum-reaction-picker" data-forum-reaction-picker>
        <summary
            class="forum-reaction-picker__trigger"
            title="Añadir reacción"
            aria-label="Añadir reacción"
        >
            <span aria-hidden="true">+🙂</span>
        </summary>

        <div class="forum-reaction-picker__menu">
            @foreach($reactionOptions as $reactionCode => $reactionOption)
                <form
                    method="POST"
                    action="{{ $reactionRoute }}"
                    data-forum-reaction-form
                    data-reaction-code="{{ $reactionCode }}"
                    class="forum-reaction-form"
                >
                    @csrf
                    <input type="hidden" name="reaction" value="{{ $reactionCode }}">
                    <button
                        type="submit"
                        class="forum-reaction-picker__option {{ $myReaction === $reactionCode ? 'is-active' : '' }}"
                        data-forum-reaction-option
                        title="{{ $reactionOption['label'] }}"
                        aria-label="{{ $reactionOption['label'] }}"
                    >
                        {{ $reactionOption['emoji'] }}
                    </button>
                </form>
            @endforeach
        </div>
    </details>
</div>
