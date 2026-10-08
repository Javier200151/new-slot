@php
    $images = collect($section['images'] ?? []);
    $topImages = $images->where('image_position', 'top')->values();
    $leftImages = $images->where('image_position', 'left')->values();
    $rightImages = $images->where('image_position', 'right')->values();
    $bottomImages = $images->where('image_position', 'bottom')->values();

    $leftWidth = $leftImages->max(fn (array $image): int => (int) ($image['image_width'] ?? 40)) ?: 0;
    $rightWidth = $rightImages->max(fn (array $image): int => (int) ($image['image_width'] ?? 40)) ?: 0;

    if ($leftImages->isNotEmpty() && $rightImages->isNotEmpty()) {
        $leftWidth = min($leftWidth, 30);
        $rightWidth = min($rightWidth, 30);
    }
@endphp

<section>
    <div
        class="briefing-section__heading briefing-rich"
        role="heading"
        aria-level="3"
    >
        {{ $section['title'] }}
    </div>

    @if($topImages->isNotEmpty())
        <div class="briefing-gallery briefing-gallery--top">
            @foreach($topImages as $image)
                <figure
                    class="briefing-gallery__image briefing-gallery__image--align-{{ $image['image_alignment'] ?? 'left' }}"
                    style="--briefing-gallery-image-width: {{ $image['image_width'] ?? 40 }}%;"
                >
                    <img
                        src="{{ $image['image'] }}"
                        alt="{{ $image['image_caption'] ?? '' }}"
                        loading="lazy"
                    >
                    @if(filled($image['image_caption'] ?? null))
                        <figcaption>{{ $image['image_caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    @endif

    <div
        class="briefing-gallery__main
            @if($leftImages->isNotEmpty()) briefing-gallery__main--has-left @endif
            @if($rightImages->isNotEmpty()) briefing-gallery__main--has-right @endif"
        style="--briefing-gallery-left-width: {{ $leftWidth }}%; --briefing-gallery-right-width: {{ $rightWidth }}%;"
    >
        @if($leftImages->isNotEmpty())
            <div class="briefing-gallery__side briefing-gallery__side--left">
                @foreach($leftImages as $image)
                    <figure class="briefing-gallery__image briefing-gallery__image--side">
                        <img
                            src="{{ $image['image'] }}"
                            alt="{{ $image['image_caption'] ?? '' }}"
                            loading="lazy"
                        >
                        @if(filled($image['image_caption'] ?? null))
                            <figcaption>{{ $image['image_caption'] }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        @endif

        <div class="briefing-section__content event-rich-content">
            <section>
                <div class="event-rich-content briefing-rich">
                    {{ $section['content'] }}
                </div>
            </section>
        </div>

        @if($rightImages->isNotEmpty())
            <div class="briefing-gallery__side briefing-gallery__side--right">
                @foreach($rightImages as $image)
                    <figure class="briefing-gallery__image briefing-gallery__image--side">
                        <img
                            src="{{ $image['image'] }}"
                            alt="{{ $image['image_caption'] ?? '' }}"
                            loading="lazy"
                        >
                        @if(filled($image['image_caption'] ?? null))
                            <figcaption>{{ $image['image_caption'] }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        @endif
    </div>

    @if($bottomImages->isNotEmpty())
        <div class="briefing-gallery briefing-gallery--bottom">
            @foreach($bottomImages as $image)
                <figure
                    class="briefing-gallery__image briefing-gallery__image--align-{{ $image['image_alignment'] ?? 'left' }}"
                    style="--briefing-gallery-image-width: {{ $image['image_width'] ?? 40 }}%;"
                >
                    <img
                        src="{{ $image['image'] }}"
                        alt="{{ $image['image_caption'] ?? '' }}"
                        loading="lazy"
                    >
                    @if(filled($image['image_caption'] ?? null))
                        <figcaption>{{ $image['image_caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    @endif
</section>
