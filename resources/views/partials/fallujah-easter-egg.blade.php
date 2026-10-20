@auth
    @if(\App\Support\FallujahEasterEgg::visible())
        @once
            <link rel="stylesheet" href="{{ asset('css/fallujah-easter-egg.css') }}?v={{ filemtime(public_path('css/fallujah-easter-egg.css')) }}">
            <div class="sqa-fallujah" data-fallujah-root>
                <button class="sqa-fallujah__beacon" type="button" data-fallujah-open
                    aria-haspopup="dialog" aria-controls="sqa-fallujah-dialog"
                    title="Señal desconocida · frecuencia histórica" aria-label="Descifrar señal de radio desconocida">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 11v10h16V11H4Zm3 4h6m-6 3h3m6-3v3M12 11l6-8m-6 8h5"/>
                        <circle cx="17.5" cy="17" r="1.5" />
                    </svg>
                    <span class="sqa-fallujah__beacon-dot" aria-hidden="true"></span>
                </button>

                <div class="sqa-fallujah__overlay" id="sqa-fallujah-dialog" data-fallujah-dialog
                    role="dialog" aria-modal="true" aria-labelledby="sqa-fallujah-title" tabindex="-1" hidden>
                    <div class="sqa-fallujah__scrim" data-fallujah-close></div>
                    <section class="sqa-fallujah__terminal">
                        <header class="sqa-fallujah__topbar">
                            <div class="sqa-fallujah__brand">
                                <span class="sqa-fallujah__live" aria-hidden="true"></span>
                                <span>ARCHIVO TÁCTICO · SQUAD ALPHA</span>
                            </div>
                            <div class="sqa-fallujah__topbar-right">
                                <span>EXPEDIENTE 001</span>
                                <button type="button" class="sqa-fallujah__close" data-fallujah-close aria-label="Cerrar expediente">×</button>
                            </div>
                        </header>

                        <div class="sqa-fallujah__body">
                            <div class="sqa-fallujah__steps" aria-label="Fases de la transmisión">
                                <span class="is-active" data-fallujah-indicator="0">01 / SEÑAL</span>
                                <span data-fallujah-indicator="1">02 / ARCHIVO</span>
                                <span data-fallujah-indicator="2">03 / INFORME</span>
                            </div>

                            <section data-fallujah-stage="0" class="sqa-fallujah__stage">
                                <div class="sqa-fallujah__eyebrow">● FRECUENCIA RECUPERADA // 27.04.2012</div>
                                <h2 id="sqa-fallujah-title" class="sqa-fallujah__heading">ASALTO EN <em>FALLUJAH</em></h2>
                                <p class="sqa-fallujah__lede">Una transmisión olvidada. Una operación que marcó el comienzo de nuestra historia.</p>
                                <div class="sqa-fallujah__data-grid">
                                    <div><small>FECHA</small><strong>27 / 04 / 2012</strong></div>
                                    <div><small>HORA DE CONVOCATORIA</small><strong>22:45 H · ZULÚ</strong></div>
                                    <div><small>MODALIDAD</small><strong>WAR IN TAKISTAN</strong></div>
                                    <div><small>ESCENARIO</small><strong>FALLUJAH</strong></div>
                                </div>
                                <div class="sqa-fallujah__intercept">
                                    <small>MENSAJE ORIGINAL · DIABLO · 27/04/2012</small>
                                    <p>«Esta noche estáis todos convocados a una partida del tipo War in Takistan en Fallujah»</p>
                                </div>
                                <div class="sqa-fallujah__actions">
                                    <button type="button" class="sqa-fallujah__action" data-fallujah-next>DESCIFRAR TRANSMISIÓN <span aria-hidden="true">→</span></button>
                                </div>
                            </section>

                            <section data-fallujah-stage="1" class="sqa-fallujah__stage" hidden>
                                <div class="sqa-fallujah__eyebrow">DOSSIER DESCLASIFICADO // ESUS → SQUAD ALPHA</div>
                                <h2 class="sqa-fallujah__heading sqa-fallujah__heading--medium">FOTOGRAFÍAS DEL <em>ORIGEN</em></h2>
                                <p class="sqa-fallujah__lede">Cinco capturas auténticas publicadas con el informe de la operación.</p>
                                @php
                                    $fallujahPictures = [
                                        ['file' => 'JUu0w.jpg', 'caption' => 'Fotografía de grupo frente al antiguo cartel del Clan ESUS.', 'alt' => 'Soldados del grupo reunidos junto a la entrada con el emblema ESUS.'],
                                        ['file' => 'wntsU.jpg', 'caption' => 'Desplazamiento en vehículo, durante la misión.', 'alt' => 'Vista interior del vehículo con un soldado en la cabina.'],
                                        ['file' => 'zW8ei.jpg', 'caption' => 'Blindado y helicóptero en el teatro de operaciones.', 'alt' => 'Blindado en primer plano y helicóptero sobrevolando la zona.'],
                                        ['file' => '0rFAN.jpg', 'caption' => 'Helicóptero militar sobre Fallujah.', 'alt' => 'Helicóptero visto desde un lateral sobre una gran ciudad.'],
                                        ['file' => 'fumOc.jpg', 'caption' => 'Vista aérea de una zona de la operación.', 'alt' => 'Helicóptero de combate sobre instalaciones y terreno abierto.'],
                                    ];
                                @endphp
                                <div class="sqa-fallujah__photo-gallery">
                                    @foreach($fallujahPictures as $index => $photo)
                                        <figure class="sqa-fallujah__photo" data-fallujah-photo="{{ $index }}" @if($index !== 0) hidden @endif>
                                            <img src="{{ asset('images/fallujah-2012/'.$photo['file']) }}" alt="{{ $photo['alt'] }}" loading="lazy">
                                            <figcaption><span>FOTO {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }} / 05</span>{{ $photo['caption'] }}</figcaption>
                                        </figure>
                                    @endforeach
                                    <div class="sqa-fallujah__photo-controls">
                                        <button type="button" data-fallujah-photo-prev aria-label="Fotografía anterior">←</button>
                                        <span data-fallujah-photo-counter aria-live="polite">01 / 05</span>
                                        <button type="button" data-fallujah-photo-next aria-label="Siguiente fotografía">→</button>
                                    </div>
                                </div>
                                <p class="sqa-fallujah__detail">MODS ORIGINALES: ACE · CBA · @bb_mercs · @bb_oa_mercs · FALLUJAH</p>
                                <div class="sqa-fallujah__actions">
                                    <button type="button" class="sqa-fallujah__action" data-fallujah-next>CONSULTAR INFORME FINAL <span aria-hidden="true">→</span></button>
                                    <button type="button" class="sqa-fallujah__subtle" data-fallujah-back>← Volver</button>
                                </div>
                            </section>

                            <section data-fallujah-stage="2" class="sqa-fallujah__stage" hidden>
                                <div class="sqa-fallujah__eyebrow">28.04.2012 // INFORME DE DIABLO</div>
                                <div class="sqa-fallujah__completed" aria-hidden="true">✓</div>
                                <h2 class="sqa-fallujah__heading sqa-fallujah__heading--center">¡MISIÓN <em>CUMPLIDA!</em></h2>
                                <p class="sqa-fallujah__lede sqa-fallujah__lede--center">El informe original destacó la organización por binomios, el orden por radio, los Humvees, el apoyo blindado y el rescate en helicóptero.</p>
                                <blockquote class="sqa-fallujah__closing-quote">«Esto funciona y lo que queda!!!» <span>— Diablo, 28/04/2012</span></blockquote>
                                <div class="sqa-fallujah__anniversary">
                                    <strong>2012 — {{ now(\App\Support\FallujahEasterEgg::ZONE)->year }}</strong>
                                    <span>{{ \App\Support\FallujahEasterEgg::anniversary() }} AÑOS DE HISTORIA. UNA MISMA FRECUENCIA.</span>
                                </div>
                                <div class="sqa-fallujah__actions sqa-fallujah__actions--center">
                                    <a class="sqa-fallujah__action" href="https://foro.squadalpha.es/viewtopic.php?t=124" target="_blank" rel="noopener noreferrer">ABRIR PUBLICACIÓN ORIGINAL ↗</a>
                                    <button type="button" class="sqa-fallujah__subtle" data-fallujah-back>← Revisar fotografías</button>
                                </div>
                            </section>
                        </div>
                        <footer class="sqa-fallujah__footnote">ARCHIVO HISTÓRICO · 27 DE ABRIL DE 2012 · SQUAD ALPHA</footer>
                    </section>
                </div>
            </div>
            <script src="{{ asset('js/fallujah-easter-egg.js') }}?v={{ filemtime(public_path('js/fallujah-easter-egg.js')) }}" defer></script>
        @endonce
    @endif
@endauth
