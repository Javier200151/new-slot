<div class="home-news-grid" data-sqa-news-list>
    @foreach($news as $item)
        @php $published = $item->published_at ?? $item->created_at; @endphp
        <article @class(['home-news-card', 'home-news-card--veterancy' => $item->category === 'veterania'])
            data-sqa-news-item data-published="{{ $published?->timestamp ?? 0 }}">
            @if($item->image)
                <div class="home-news-card__media">
                    <img loading="lazy" src="{{ asset('storage/' . $item->image) }}" alt="Imagen del anuncio: {{ $item->title }}">
                </div>
            @endif
            <div class="home-news-card__body">
                <div class="home-news-card__meta">
                    <span>{{ $item->category_label }}</span>
                    <span class="home-news-new" data-sqa-news-new hidden>Nuevo</span>
                    @if($published)
                        <time datetime="{{ $published->toDateString() }}">{{ $published->format('d · m · Y') }}</time>
                    @endif
                </div>
                <h3>{{ $item->title }}</h3>
                @if($item->excerpt)
                    <div class="bbcode-rich forum-rich">{!! \App\Support\BbcodeMarkup::render($item->excerpt) !!}</div>
                @endif
                @if($item->category === 'veterania' && !empty($item->veterancy_groups))
                    <div class="home-veterancy-groups" aria-label="Miembros reconocidos por veteranía">
                        @foreach(['gold' => 'Oro', 'silver' => 'Plata', 'bronze' => 'Bronce'] as $level => $label)
                            @if(!empty($item->veterancy_groups[$level]))
                                <section class="home-veterancy-group home-veterancy-group--{{ $level }}">
                                    <div class="home-veterancy-group__heading">
                                        <span class="home-veterancy-group__medal" aria-hidden="true">★</span>
                                        <strong>Veteranía {{ $label }}</strong>
                                        <span class="home-veterancy-group__count">{{ count($item->veterancy_groups[$level]) }}</span>
                                    </div>
                                    <ul class="home-veterancy-group__members">
                                        @foreach($item->veterancy_groups[$level] as $nick)
                                            <li>{{ $nick }}</li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
                        @endforeach
                    </div>
                @endif
                @if($item->body)
                    <div class="home-news-card__content bbcode-rich forum-rich">{!! \App\Support\BbcodeMarkup::render($item->body) !!}</div>
                @endif
                @if($item->external_url)
                    <a href="{{ $item->external_url }}" class="home-text-link" @if(\Illuminate\Support\Str::startsWith($item->external_url, ['http://', 'https://'])) target="_blank" rel="noopener noreferrer" @endif>
                        Ver más <span aria-hidden="true">↗</span>
                    </a>
                @endif
            </div>
        </article>
    @endforeach
</div>
