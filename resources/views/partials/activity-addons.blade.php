<details
    id="addons"
    class="event-detail__section event-detail__collapsible event-detail__addons-section"
>
    <summary class="event-detail__collapsible-summary">
        <div>
            <span>Addons</span>
            <small>Mods utilizados por la actividad</small>
        </div>

        <div class="event-detail__addons-summary-actions">
            <strong class="event-detail__addons-count">
                {{ $addons->count() }} {{ $addons->count() === 1 ? 'addon' : 'addons' }}
            </strong>
            <a
                class="event-detail__addons-download"
                href="{{ route('activities.addons.download', $activity) }}"
                download
                aria-label="Descargar HTML de los addons"
                onclick="event.stopPropagation()"
            >Descargar HTML</a>
        </div>
    </summary>

    <div class="event-detail__collapsible-content">
        <div class="event-detail__table-wrap">
            <table class="event-detail__table event-detail__addons-table">
                <thead>
                    <tr><th>Addon</th></tr>
                </thead>
                <tbody>
                    @foreach($addons as $addon)
                        <tr>
                            <td>
                                @if($url = \App\Support\ActivityAddonsHtml::safeUrl($addon->download_url))
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="Abrir enlace de {{ $addon->name }}">
                                        {{ $addon->name }}
                                    </a>
                                @else
                                    {{ $addon->name }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</details>
