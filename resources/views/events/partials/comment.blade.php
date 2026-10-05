@php
    $author = $comment->user;
    $replies = $commentsByParent->get($comment->id, collect());
    $authorImage = $author?->image
        ? asset('storage/' . $author->image)
        : asset('images/sqa-shield-white.png');
@endphp

<article @class(['event-comment', 'is-reply' => $depth > 0, 'is-pinned' => $comment->is_pinned])>
    <header class="event-comment__header">
        <div>
            <x-user-link
                :user="$author"
                class="event-comment__author"
                @style([
                    '--member-group-color: '
                    . ($author?->mainSqaGroup?->color ?? '')
                    => filled(
                        $author?->mainSqaGroup?->color
                    ),
                ])
            />

            @if($comment->is_pinned)
                <span class="event-comment__pinned">Fijado</span>
            @endif
        </div>

        <div class="event-comment__user-media">
            <time datetime="{{ $comment->updated_at?->toIso8601String() }}">
                {{ $comment->updated_at?->format('d/m/Y H:i') }}
            </time>
            <img
                src="{{ $authorImage }}"
                alt="Imagen de {{ $author?->nick ?? 'usuario eliminado' }}"
                loading="lazy"
            >
        </div>
    </header>

    <div class="event-comment__body bbcode-rich forum-rich">{!! \App\Support\BbcodeMarkup::render($comment->comment) !!}</div>

    @auth
        @unless($isReadOnly ?? false)
        <div class="event-comment__actions">
            <details class="event-comment__reply">
                <summary>Responder</summary>
                <form method="POST" action="{{ route('events.comments.store', $comment->event_id) }}">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                    @include('partials.bbcode-editor', [
                        'id' => 'event-comment-reply-' . $comment->id,
                        'name' => 'comment',
                        'label' => 'Responder a ' . ($author?->nick ?? 'este comentario'),
                        'value' => '',
                        'rows' => 3,
                        'maxlength' => 5000,
                        'required' => true,
                        'placeholder' => 'Escribe tu respuesta...',
                    ])
                    <button type="submit">Publicar respuesta</button>
                </form>
            </details>

            @if(auth()->id() === $comment->user_id)
                <details class="event-comment__edit">
                    <summary>Editar comentario</summary>
                    <form method="POST" action="{{ route('events.comments.update', [$comment->event_id, $comment]) }}">
                        @csrf
                        @method('PATCH')
                        @include('partials.bbcode-editor', [
                            'id' => 'event-comment-' . $comment->id,
                            'name' => 'comment',
                            'label' => 'Editar comentario',
                            'value' => $comment->comment,
                            'rows' => 4,
                            'maxlength' => 5000,
                            'required' => true,
                        ])
                        <button type="submit">Guardar cambios</button>
                    </form>
                </details>
            @endif

            @can('delete', $comment)
                <form
                    method="POST"
                    action="{{ route('events.comments.destroy', [$comment->event_id, $comment]) }}"
                    class="event-comment__delete"
                    data-confirm-message="{{ $replies->isNotEmpty()
                        ? '¿Eliminar este comentario? También se eliminarán todas sus respuestas.'
                        : '¿Eliminar este comentario?' }}"
                    onsubmit="return confirm(this.dataset.confirmMessage);"
                >
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="event-comment__delete-button"
                        title="Eliminar comentario"
                        aria-label="Eliminar comentario"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-2 6h10l-1 11H8L7 9Zm3 2v7h2v-7h-2Zm4 0v7h2v-7h-2Z" />
                        </svg>
                    </button>
                </form>
            @endcan
        </div>
        @endunless
    @endauth

    @if($author?->firma && ! $author->trashed())
        <iframe
            class="event-comment__signature"
            src="{{ $author->firma }}"
            title="Firma de {{ $author->nick }}"
            loading="lazy"
            scrolling="no"
            data-signature-frame
        ></iframe>
    @endif

    @if($replies->isNotEmpty())
        <div class="event-comment__replies">
            @foreach($replies as $reply)
                @include('events.partials.comment', [
                    'comment' => $reply,
                    'commentsByParent' => $commentsByParent,
                    'depth' => $depth + 1,
                ])
            @endforeach
        </div>
    @endif
</article>
