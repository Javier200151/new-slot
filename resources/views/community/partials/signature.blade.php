@if($author?->firma && ! $author->trashed() && $author->status?->name !== 'USUARIO')
    <footer class="forum-signature">
        <iframe
            src="{{ $author->getSignatureUrl() }}?fit=1"
            title="Firma de {{ $author->nick }}"
            loading="lazy"
            scrolling="no"
        ></iframe>
    </footer>
@endif
