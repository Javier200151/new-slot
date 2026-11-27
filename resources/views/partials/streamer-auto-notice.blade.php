@auth
    <dialog class="sqa-streamer-notice" data-streamer-notice data-notice-url="{{ route('streams.mine.unpublished') }}">
        <div class="sqa-streamer-notice__panel">
            <h2>Hemos detectado que estás en directo</h2>
            <p data-streamer-notice-message></p>
            <div class="sqa-streamer-notice__actions">
                <button type="button" data-streamer-notice-dismiss>De acuerdo</button>
                <a href="{{ route('streams.index') }}#panel-streamer" data-streamer-notice-panel>Ir a Panel de Streamer</a>
            </div>
        </div>
    </dialog>
    <style>
        .sqa-streamer-notice { max-width:min(440px,calc(100vw - 32px)); width:100%; padding:0; border:1px solid #3b4557; border-radius:14px; background:#151b26; color:#f5f7fa; box-shadow:0 20px 70px #0009; }
        .sqa-streamer-notice::backdrop { background:rgba(0,0,0,.72); }
        .sqa-streamer-notice__panel { padding:25px; }
        .sqa-streamer-notice h2 { font-size:1.3rem; line-height:1.3; margin:0 0 12px; color:#fff; }
        .sqa-streamer-notice p { margin:0 0 24px; color:#d3dbe7; line-height:1.55; }
        .sqa-streamer-notice__actions { display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; }
        .sqa-streamer-notice__actions button,.sqa-streamer-notice__actions a { border:1px solid #586376; border-radius:8px; padding:10px 14px; font:inherit; font-size:.9rem; cursor:pointer; text-decoration:none; }
        .sqa-streamer-notice__actions button { color:#fff; background:#252f3d; }
        .sqa-streamer-notice__actions a { background:#f59e0b; border-color:#f59e0b; color:#181818; font-weight:700; }
    </style>
    <script src="{{ asset('js/streamer-auto-notice.js') }}?v=1" defer></script>
@endauth
