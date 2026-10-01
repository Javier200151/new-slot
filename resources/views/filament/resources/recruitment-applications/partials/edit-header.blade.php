@php
    $actions = collect($this->getCachedHeaderActions())
        ->keyBy(fn ($action) => $action->getName());

    $primaryNames = ['approve', 'discard', 'deleteApplication'];
    $secondaryNames = ['setTier', 'assignInterviewer', 'linkMatchedUser', 'resetDecision'];

    $breadcrumbs = filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [];
    $heading = $this->getHeading();
    $subheading = $this->getSubheading();
@endphp

<style>
    #recruitment-edit-header.fi-header {
        align-items: flex-start !important;
        gap: 1rem !important;
    }

    #recruitment-edit-header .recruitment-header-actions {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: .55rem !important;
        min-width: 0;
    }

    #recruitment-edit-header .recruitment-header-actions__row {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        gap: .5rem !important;
        min-width: 0;
    }

    #recruitment-edit-header .recruitment-header-actions__primary {
        order: 1;
        margin-left: 2.25rem;
    }

    #recruitment-edit-header .recruitment-header-actions__secondary {
        order: 2;
    }

    @media (max-width: 1050px) {
        #recruitment-edit-header.fi-header {
            flex-direction: column !important;
        }

        #recruitment-edit-header .recruitment-header-actions {
            width: 100%;
        }

        #recruitment-edit-header .recruitment-header-actions__primary {
            margin-left: 0;
        }
    }
</style>

<header
    id="recruitment-edit-header"
    @class(['fi-header', 'fi-header-has-breadcrumbs' => filled($breadcrumbs)])
>
    <div>
        @if ($breadcrumbs)
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        @endif

        @if (filled($heading))
            <h1 class="fi-header-heading">{{ $heading }}</h1>
        @endif

        @if (filled($subheading))
            <p class="fi-header-subheading">{{ $subheading }}</p>
        @endif
    </div>

    <div class="recruitment-header-actions">
        <div class="recruitment-header-actions__row recruitment-header-actions__primary">
            @foreach ($primaryNames as $name)
                @php($action = $actions->get($name))

                @if ($action && $action->isVisible())
                    {{ $action }}
                @endif
            @endforeach
        </div>

        <div class="recruitment-header-actions__row recruitment-header-actions__secondary">
            @foreach ($secondaryNames as $name)
                @php($action = $actions->get($name))

                @if ($action && $action->isVisible())
                    {{ $action }}
                @endif
            @endforeach
        </div>
    </div>
</header>
