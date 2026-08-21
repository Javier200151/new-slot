@php
    $author = $comment->user;
    $replies = $commentsByParent->get($comment->id, collect());
@endphp

<article @class(['event-comment', 'is-reply' => $depth > 0, 'is-pinned' => $comment->is_pinned])>
    <header class="event-comment__header">
        <div>
            <strong
                class="event-comment__author"
                @style([
                    '--member-group-color: ' . ($author?->mainSqaGroup?->color ?? '') => filled($author?->mainSqaGroup?->color),
                ])
            >
                {{ $author?->nick ?? 'Usuario eliminado' }}
            </strong>

            @if($comment->is_pinned)
                <span class="event-comment__pinned">Fijado</span>
            @endif
        </div>

        <time datetime="{{ $comment->updated_at?->toIso8601String() }}">
            {{ $comment->updated_at?->format('d/m/Y H:i') }}
        </time>
    </header>

    <p class="event-comment__body">{{ $comment->comment }}</p>

    @auth
        <div class="event-comment__actions">
            <details class="event-comment__reply">
                <summary>Responder</summary>
                <form method="POST" action="{{ route('events.comments.store', $comment->event_id) }}">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                    <label class="sr-only" for="event-comment-reply-{{ $comment->id }}">
                        Responder a {{ $author?->nick ?? 'este comentario' }}
                    </label>
                    <textarea
                        id="event-comment-reply-{{ $comment->id }}"
                        name="comment"
                        rows="3"
                        maxlength="5000"
                        required
                        placeholder="Escribe tu respuesta..."
                    ></textarea>
                    <button type="submit">Publicar respuesta</button>
                </form>
            </details>

            @if(auth()->id() === $comment->user_id)
                <details class="event-comment__edit">
                    <summary>Editar comentario</summary>
                    <form method="POST" action="{{ route('events.comments.update', [$comment->event_id, $comment]) }}">
                        @csrf
                        @method('PATCH')
                        <label class="sr-only" for="event-comment-{{ $comment->id }}">Editar comentario</label>
                        <textarea
                            id="event-comment-{{ $comment->id }}"
                            name="comment"
                            rows="4"
                            maxlength="5000"
                            required
                        >{{ $comment->comment }}</textarea>
                        <button type="submit">Guardar cambios</button>
                    </form>
                </details>
            @endif
        </div>
    @endauth

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
