<div class="ns-tg-preview-wrap">
    <div class="ns-tg-phone">
        <header class="ns-tg-phone__header">
            <div class="ns-tg-phone__avatar">A</div>
            <div>
                <strong>= ALPHA FORCE NETWORK =</strong>
                <span>canal</span>
            </div>
        </header>

        <div class="ns-tg-phone__chat">
            <article class="ns-tg-message">
                <div class="ns-tg-message__author">Squad ALPHA</div>
                <div class="ns-tg-message__body">{!! $preview['html'] ?? '' !!}</div>
                <div class="ns-tg-message__time">19:05</div>
            </article>
        </div>
    </div>

    <div class="ns-tg-preview-copy">
        <strong>{{ $preview['title'] ?? 'Previsualización Telegram' }}</strong>
        <p>{{ $preview['note'] ?? '' }}</p>
        <small>La simulación intenta reproducir el formato MarkdownV2 que verá el usuario. No envía ningún mensaje.</small>
    </div>
</div>

<style>
    .ns-tg-preview-wrap {
        display: grid;
        grid-template-columns: minmax(300px, 460px) minmax(220px, 1fr);
        gap: 1.25rem;
        align-items: start;
        width: 100%;
    }

    .ns-tg-phone {
        overflow: hidden;
        border: 1px solid rgba(255,255,255,.09);
        border-radius: 1rem;
        background: #0e1621;
        box-shadow: 0 18px 45px rgba(0,0,0,.22);
    }

    .ns-tg-phone__header {
        display: flex;
        gap: .7rem;
        align-items: center;
        padding: .8rem 1rem;
        background: #17212b;
        border-bottom: 1px solid rgba(255,255,255,.06);
    }

    .ns-tg-phone__avatar {
        display: grid;
        width: 2.2rem;
        height: 2.2rem;
        place-items: center;
        border-radius: 999px;
        background: #f59e0b;
        color: #101318;
        font-weight: 900;
    }

    .ns-tg-phone__header strong,
    .ns-tg-phone__header span {
        display: block;
    }

    .ns-tg-phone__header > div:last-child {
        min-width: 0;
    }

    .ns-tg-phone__header strong {
        overflow-wrap: anywhere;
        color: #f8fafc;
        font-size: .9rem;
    }

    .ns-tg-phone__header span {
        margin-top: .05rem;
        color: #8fa3b7;
        font-size: .72rem;
    }

    .ns-tg-phone__chat {
        min-height: 390px;
        padding: 1rem;
        background-color: #0e1621;
        background-image:
            radial-gradient(circle at 20% 20%, rgba(255,255,255,.025) 0 2px, transparent 2px),
            radial-gradient(circle at 75% 45%, rgba(255,255,255,.02) 0 2px, transparent 2px);
        background-size: 52px 52px, 68px 68px;
    }

    .ns-tg-message {
        position: relative;
        max-width: 92%;
        padding: .72rem .85rem 1.25rem;
        border-radius: .7rem .7rem .7rem .22rem;
        background: #182533;
        color: #e7edf3;
        font-size: .82rem;
        line-height: 1.48;
        white-space: normal;
    }

    .ns-tg-message__author {
        margin-bottom: .35rem;
        color: #f6b73c;
        font-size: .76rem;
        font-weight: 800;
    }

    .ns-tg-message__body,
    .ns-tg-message__body * {
        min-width: 0;
        max-width: 100%;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .ns-tg-message__body a {
        color: #6ab7ff;
        text-decoration: none;
        word-break: break-all;
    }

    .ns-tg-message__body strong {
        color: #ffffff;
    }

    .ns-tg-message__time {
        position: absolute;
        right: .65rem;
        bottom: .35rem;
        color: #7e91a4;
        font-size: .65rem;
    }

    .ns-tg-preview-copy {
        padding: .9rem 0;
    }

    .ns-tg-preview-copy strong {
        color: #f8fafc;
        font-size: .95rem;
    }

    .ns-tg-preview-copy p,
    .ns-tg-preview-copy small {
        display: block;
        color: #94a3b8;
        line-height: 1.55;
    }

    .ns-tg-preview-copy p {
        margin: .45rem 0 .7rem;
        font-size: .82rem;
    }

    .ns-tg-preview-copy small {
        font-size: .75rem;
    }

    @media (max-width: 900px) {
        .ns-tg-preview-wrap {
            grid-template-columns: 1fr;
        }
    }
</style>
