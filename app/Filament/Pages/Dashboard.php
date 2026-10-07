<?php

namespace App\Filament\Pages;

use App\Filament\Pages\EventCalendar;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\CommunityPost;
use App\Models\Event;
use App\Models\MemberProcedureSetting;
use App\Models\PersonalDashboard as PersonalDashboardModel;
use App\Models\PersonalDashboardWidget;
use App\Models\User;
use App\Services\EventCalendarDataService;
use App\Services\PersonalDashboardService;
use App\Services\MemberProcedures\ProcedureNotificationService;
use App\Services\MemberProcedures\WeeklyActivityTelegramService;
use App\Support\CommunityForumCategory;
use App\Support\PersonalDashboardWidgetRegistry;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class Dashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.pages.personal-dashboard';

    protected Width | string | null $maxContentWidth = Width::Full;

    public ?int $activeDashboardId = null;

    public bool $editing = false;

    public string $newDashboardName = '';

    public string $renameDashboardName = '';

    public ?int $configuringWidgetId = null;

    /** @var array<int, array{label: string, url: string}> */
    public array $quickLinksEditor = [];

    public string $reminderText = '';

    public string $quickSearch = '';

    public bool $weeklyActivityPanelOpen = false;

    /** @var array<string, mixed> */
    public array $weeklyActivityPreview = [];

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $dashboard = app(PersonalDashboardService::class)->activeFor($user);

        $this->activeDashboardId = (int) $dashboard->id;
        $this->loadDashboardState();
    }

    public function getTitle(): string
    {
        return 'Dashboard';
    }

    public function dashboards(): Collection
    {
        return PersonalDashboardModel::query()
            ->where('user_id', auth()->id())
            ->orderBy('id')
            ->get();
    }

    public function activeDashboard(): PersonalDashboardModel
    {
        /** @var User $user */
        $user = auth()->user();

        if ($this->activeDashboardId) {
            $dashboard = PersonalDashboardModel::query()
                ->where('user_id', $user->id)
                ->whereKey($this->activeDashboardId)
                ->first();

            if ($dashboard) {
                return $dashboard;
            }
        }

        $dashboard = app(PersonalDashboardService::class)->activeFor($user);
        $this->activeDashboardId = (int) $dashboard->id;

        return $dashboard;
    }

    public function dashboardWidgets(): Collection
    {
        return $this->activeDashboard()
            ->widgets()
            ->get()
            ->filter(fn (PersonalDashboardWidget $widget): bool => PersonalDashboardWidgetRegistry::canUse($widget->type))
            ->values();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function availableWidgetDefinitions(): array
    {
        $existing = $this->activeDashboard()
            ->widgets()
            ->pluck('type')
            ->all();

        return collect(PersonalDashboardWidgetRegistry::definitions())
            ->reject(fn (array $definition, string $type): bool => in_array($type, $existing, true))
            ->filter(fn (array $definition, string $type): bool => PersonalDashboardWidgetRegistry::canUse($type))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function widgetDefinition(string $type): ?array
    {
        return PersonalDashboardWidgetRegistry::definition($type);
    }

    public function toggleEditing(): void
    {
        $this->editing = ! $this->editing;
        $this->configuringWidgetId = null;

        if ($this->editing) {
            $this->renameDashboardName = $this->activeDashboard()->name;
        }
    }

    public function switchDashboard(int $dashboardId): void
    {
        /** @var User $user */
        $user = auth()->user();
        $dashboard = app(PersonalDashboardService::class)->activate($user, $dashboardId);

        $this->activeDashboardId = (int) $dashboard->id;
        $this->editing = false;
        $this->configuringWidgetId = null;
        $this->quickSearch = '';
        $this->loadDashboardState();
    }

    public function createDashboard(): void
    {
        /** @var User $user */
        $user = auth()->user();
        if ($this->dashboards()->count() >= 20) {
            $this->notifyError('Puedes guardar un máximo de 20 dashboards personales.');
            return;
        }

        $name = $this->validatedDashboardName($this->newDashboardName);

        if ($name === null) {
            return;
        }

        if ($this->dashboardNameExists($name)) {
            $this->notifyError('Ya tienes un dashboard con ese nombre.');
            return;
        }

        $dashboard = app(PersonalDashboardService::class)->create($user, $name);
        $this->activeDashboardId = (int) $dashboard->id;
        $this->newDashboardName = '';
        $this->editing = true;
        $this->loadDashboardState();

        Notification::make()
            ->success()
            ->title('Dashboard creado')
            ->body('Está vacío para que añadas únicamente los widgets que quieras.')
            ->send();
    }

    public function duplicateDashboard(): void
    {
        /** @var User $user */
        $user = auth()->user();
        if ($this->dashboards()->count() >= 20) {
            $this->notifyError('Puedes guardar un máximo de 20 dashboards personales.');
            return;
        }

        $source = $this->activeDashboard()->load('widgets');
        $name = $this->uniqueDashboardName('Copia de ' . $source->name);

        $copy = app(PersonalDashboardService::class)->duplicate($user, $source, $name);
        $this->activeDashboardId = (int) $copy->id;
        $this->editing = true;
        $this->loadDashboardState();

        Notification::make()
            ->success()
            ->title('Dashboard duplicado')
            ->body('Puedes modificar la copia sin afectar al original.')
            ->send();
    }

    public function renameDashboard(): void
    {
        $dashboard = $this->activeDashboard();
        $name = $this->validatedDashboardName($this->renameDashboardName);

        if ($name === null) {
            return;
        }

        if ($this->dashboardNameExists($name, (int) $dashboard->id)) {
            $this->notifyError('Ya tienes otro dashboard con ese nombre.');
            return;
        }

        $dashboard->update(['name' => $name]);
        $this->renameDashboardName = $name;

        Notification::make()->success()->title('Nombre actualizado')->send();
    }

    public function deleteDashboard(): void
    {
        $dashboards = $this->dashboards();
        if ($dashboards->count() <= 1) {
            $this->notifyError('Debes conservar al menos un dashboard.');
            return;
        }

        $dashboard = $this->activeDashboard();
        $dashboard->delete();

        /** @var User $user */
        $user = auth()->user();
        $next = PersonalDashboardModel::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->firstOrFail();

        $next = app(PersonalDashboardService::class)->activate($user, (int) $next->id);
        $this->activeDashboardId = (int) $next->id;
        $this->editing = false;
        $this->loadDashboardState();

        Notification::make()->success()->title('Dashboard eliminado')->send();
    }

    public function addWidget(string $type): void
    {
        $definition = PersonalDashboardWidgetRegistry::definition($type);
        if (! $definition || ! PersonalDashboardWidgetRegistry::canUse($type)) {
            abort(403);
        }

        $dashboard = $this->activeDashboard();
        $position = ((int) $dashboard->widgets()->max('position')) + 1;

        $widget = $dashboard->widgets()->firstOrCreate(
            ['type' => $type],
            [
                'position' => $position,
                'size' => $definition['default_size'] ?? PersonalDashboardWidget::SIZE_WIDE,
                'settings' => [],
            ],
        );

        if ($type === PersonalDashboardWidgetRegistry::REMINDERS) {
            $this->reminderText = (string) (($widget->settings ?? [])['text'] ?? '');
        }

        Notification::make()
            ->success()
            ->title('Widget añadido')
            ->body((string) $definition['label'])
            ->send();
    }

    public function removeWidget(int $widgetId): void
    {
        $widget = $this->ownedWidget($widgetId);
        $label = (string) (PersonalDashboardWidgetRegistry::definition($widget->type)['label'] ?? 'Widget');
        $widget->delete();
        $this->normalizePositions();

        if ($this->configuringWidgetId === $widgetId) {
            $this->configuringWidgetId = null;
            $this->quickLinksEditor = [];
        }

        Notification::make()->success()->title($label . ' eliminado')->send();
    }

    public function setWidgetSize(int $widgetId, string $size): void
    {
        abort_unless($this->editing, 403);

        $widget = $this->ownedWidget($widgetId);
        $options = PersonalDashboardWidgetRegistry::sizeOptions($widget->type);
        abort_unless(array_key_exists($size, $options), 422);

        $widget->update(['size' => $size]);
    }

    /** @return array<string, string> */
    public function widgetSizeOptions(string $type): array
    {
        return PersonalDashboardWidgetRegistry::sizeOptions($type);
    }

    /** @return array{0:int,1:int} */
    public function widgetDimensions(PersonalDashboardWidget $widget): array
    {
        return PersonalDashboardWidgetRegistry::sizeDimensions($widget->type, $widget->size);
    }

    /**
     * @param array<int, int|string> $order
     */
    public function saveWidgetOrder(array $order): void
    {
        if (! $this->editing) {
            return;
        }

        $dashboard = $this->activeDashboard();
        $allWidgets = $dashboard->widgets()->get();
        $visibleIds = $allWidgets
            ->filter(fn (PersonalDashboardWidget $widget): bool => PersonalDashboardWidgetRegistry::canUse($widget->type))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $hiddenIds = $allWidgets
            ->reject(fn (PersonalDashboardWidget $widget): bool => PersonalDashboardWidgetRegistry::canUse($widget->type))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $order = array_values(array_unique(array_map('intval', $order)));

        if (array_diff($order, $visibleIds) !== [] || array_diff($visibleIds, $order) !== []) {
            abort(403);
        }

        DB::transaction(function () use ($dashboard, $order, $hiddenIds): void {
            $position = 0;
            foreach ([...$order, ...$hiddenIds] as $id) {
                $dashboard->widgets()
                    ->whereKey($id)
                    ->update(['position' => $position++]);
            }
        });
    }

    public function configureWidget(int $widgetId): void
    {
        $widget = $this->ownedWidget($widgetId);
        $definition = PersonalDashboardWidgetRegistry::definition($widget->type);

        if (! ($definition['configurable'] ?? false)) {
            return;
        }

        $this->configuringWidgetId = $widgetId;
        $this->quickLinksEditor = collect(($widget->settings ?? [])['links'] ?? [])
            ->map(fn ($link): array => [
                'label' => trim((string) ($link['label'] ?? '')),
                'url' => trim((string) ($link['url'] ?? '')),
            ])
            ->values()
            ->all();

        if ($this->quickLinksEditor === []) {
            $this->quickLinksEditor[] = ['label' => '', 'url' => ''];
        }
    }

    public function cancelWidgetConfiguration(): void
    {
        $this->configuringWidgetId = null;
        $this->quickLinksEditor = [];
    }

    public function addQuickLinkRow(): void
    {
        if (count($this->quickLinksEditor) >= 12) {
            $this->notifyError('Puedes guardar un máximo de 12 accesos rápidos.');
            return;
        }

        $this->quickLinksEditor[] = ['label' => '', 'url' => ''];
    }

    public function removeQuickLinkRow(int $index): void
    {
        unset($this->quickLinksEditor[$index]);
        $this->quickLinksEditor = array_values($this->quickLinksEditor);
    }

    public function saveQuickLinks(): void
    {
        if (! $this->configuringWidgetId) {
            return;
        }

        $widget = $this->ownedWidget($this->configuringWidgetId);
        abort_unless($widget->type === PersonalDashboardWidgetRegistry::QUICK_LINKS, 403);

        $links = [];
        foreach ($this->quickLinksEditor as $index => $link) {
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($label === '' && $url === '') {
                continue;
            }

            if ($label === '' || mb_strlen($label) > 60) {
                $this->notifyError('Cada acceso rápido necesita un nombre de hasta 60 caracteres.');
                return;
            }

            if (! $this->isAllowedQuickLinkUrl($url)) {
                $this->notifyError('La URL del acceso ' . ($index + 1) . ' no es válida. Usa /ruta-interna o http/https.');
                return;
            }

            $links[] = ['label' => $label, 'url' => $url];
        }

        $widget->update(['settings' => ['links' => $links]]);
        $this->configuringWidgetId = null;
        $this->quickLinksEditor = [];

        Notification::make()->success()->title('Accesos rápidos guardados')->send();
    }

    public function saveReminder(): void
    {
        $widget = $this->activeDashboard()
            ->widgets()
            ->where('type', PersonalDashboardWidgetRegistry::REMINDERS)
            ->first();

        if (! $widget) {
            return;
        }

        $text = Str::limit($this->reminderText, 5000, '');
        $this->reminderText = $text;
        $widget->update(['settings' => ['text' => $text]]);

        Notification::make()->success()->title('Recordatorios guardados')->send();
    }

    /**
     * La vista compacta comparte exactamente la misma fuente de datos que
     * Filament > Eventos > Calendario. En el dashboard es solo lectura.
     *
     * @return array<string, mixed>
     */
    public function calendarData(): array
    {
        return app(EventCalendarDataService::class)->month(now()->startOfMonth());
    }

    public function eventCalendarUrl(): string
    {
        return EventCalendar::getUrl();
    }

    public function canUseWeeklyActivityTelegram(): bool
    {
        return (bool) auth()->user()?->can('member-procedure-settings.update');
    }

    /** @return array<string, mixed> */
    public function weeklyActivityWindow(): array
    {
        return app(WeeklyActivityTelegramService::class)->window();
    }

    public function prepareWeeklyActivityTelegram(): void
    {
        abort_unless($this->canUseWeeklyActivityTelegram(), 403);

        try {
            $setting = MemberProcedureSetting::current();
            $preview = app(WeeklyActivityTelegramService::class)->preview($setting);
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError($exception->getMessage());
            return;
        }

        if (! ($preview['window_open'] ?? false)) {
            $this->notifyError('La actividad semanal solo puede enviarse desde el domingo a las 00:00 hasta el lunes a las 18:00.');
            return;
        }

        $this->weeklyActivityPreview = $preview;
        $this->weeklyActivityPanelOpen = true;
    }

    public function closeWeeklyActivityTelegram(): void
    {
        $this->weeklyActivityPanelOpen = false;
        $this->weeklyActivityPreview = [];
    }

    public function sendWeeklyActivityTelegram(): void
    {
        abort_unless($this->canUseWeeklyActivityTelegram(), 403);

        try {
            $setting = MemberProcedureSetting::current();
            $preferredEnding = array_key_exists('ending', $this->weeklyActivityPreview)
                ? (string) $this->weeklyActivityPreview['ending']
                : null;

            // Revalidamos justo antes de enviar: si alguien ha cambiado los
            // eventos desde que se abrió la previsualización, el bloqueo se
            // aplica sobre el estado real actual.
            $freshPreview = app(WeeklyActivityTelegramService::class)->preview($setting, null, $preferredEnding);
            $this->weeklyActivityPreview = $freshPreview;

            if (! ($freshPreview['can_send'] ?? false)) {
                Notification::make()
                    ->danger()
                    ->title('No se puede enviar la actividad semanal')
                    ->body(implode("\n", (array) ($freshPreview['blocking_errors'] ?? [])))
                    ->persistent()
                    ->send();
                return;
            }

            $result = app(WeeklyActivityTelegramService::class)->send($setting, null, $preferredEnding);
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->danger()
                ->title('No se pudo enviar la actividad semanal')
                ->body($exception->getMessage())
                ->persistent()
                ->send();
            return;
        }

        $this->weeklyActivityPanelOpen = false;
        $this->weeklyActivityPreview = [];

        Notification::make()
            ->success()
            ->title('Actividad semanal enviada a Telegram')
            ->body('Mensaje publicado en = ALPHA FORCE NETWORK =' . (filled($result['message_id'] ?? null) ? ' · mensaje #' . $result['message_id'] : '') . '.')
            ->send();
    }

    public function procedureNotifications(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return app(ProcedureNotificationService::class)
            ->visibleTo($user)
            ->with(['procedure.user'])
            ->latest('created_at')
            ->limit(20)
            ->get();
    }

    public function acknowledgeProcedureNotification(int $notificationId): void
    {
        /** @var User $user */
        $user = auth()->user();
        app(ProcedureNotificationService::class)->acknowledgeFor($user, $notificationId);

        Notification::make()->success()->title('Aviso marcado como revisado')->send();
    }

    /**
     * @return array<int, array{type: string, label: string, meta: string, url: string}>
     */
    public function quickSearchResults(): array
    {
        $term = trim($this->quickSearch);
        if (strlen($term) < 2) {
            return [];
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        $results = [];

        if (UserResource::canViewAny()) {
            UserResource::getEloquentQuery()
                ->where('nick', 'like', $like)
                ->orderBy('nick')
                ->limit(5)
                ->get()
                ->each(function (User $user) use (&$results): void {
                    $results[] = [
                        'type' => 'Miembro',
                        'label' => (string) $user->nick,
                        'meta' => (string) ($user->status?->name ?? ''),
                        'url' => UserResource::canEdit($user)
                            ? UserResource::getUrl('edit', ['record' => $user])
                            : route('users.show', ['user' => $user->nick]),
                    ];
                });
        }

        if (EventResource::canViewAny()) {
            EventResource::getEloquentQuery()
                ->where('name', 'like', $like)
                ->orderByDesc('date')
                ->limit(5)
                ->get()
                ->each(function (Event $event) use (&$results): void {
                    $results[] = [
                        'type' => 'Evento',
                        'label' => (string) $event->name,
                        'meta' => $event->date?->format('d/m/Y H:i') ?? '',
                        'url' => EventResource::canEdit($event)
                            ? EventResource::getUrl('edit', ['record' => $event])
                            : route('events.show', $event),
                    ];
                });
        }

        /** @var User $viewer */
        $viewer = auth()->user();
        CommunityPost::query()
            ->whereNotNull('forum_category_id')
            ->where('title', 'like', $like)
            ->with('forumCategory')
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->filter(function (CommunityPost $post) use ($viewer): bool {
                $category = $post->forumCategory;

                return $category
                    && ! $category->isDiary()
                    && CommunityForumCategory::canView($viewer, (string) $category->slug);
            })
            ->take(5)
            ->each(function (CommunityPost $post) use (&$results): void {
                $category = $post->forumCategory;
                $results[] = [
                    'type' => 'Foro',
                    'label' => (string) $post->title,
                    'meta' => (string) $category->title,
                    'url' => route('community.forum.show', [$category->slug, $post]),
                ];
            });

        return $results;
    }

    private function ownedWidget(int $widgetId): PersonalDashboardWidget
    {
        return $this->activeDashboard()
            ->widgets()
            ->whereKey($widgetId)
            ->firstOrFail();
    }

    private function normalizePositions(): void
    {
        $dashboard = $this->activeDashboard();
        $dashboard->widgets()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->each(fn (PersonalDashboardWidget $widget, int $position) => $widget->update(['position' => $position]));
    }

    private function loadDashboardState(): void
    {
        $dashboard = $this->activeDashboard();
        $this->renameDashboardName = (string) $dashboard->name;
        $this->quickSearch = '';
        $this->configuringWidgetId = null;
        $this->quickLinksEditor = [];

        $reminders = $dashboard->widgets()
            ->where('type', PersonalDashboardWidgetRegistry::REMINDERS)
            ->first();

        $this->reminderText = (string) (($reminders?->settings ?? [])['text'] ?? '');
    }

    private function validatedDashboardName(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 80) {
            $this->notifyError('El nombre del dashboard debe tener entre 1 y 80 caracteres.');
            return null;
        }

        return $value;
    }

    private function dashboardNameExists(string $name, ?int $exceptId = null): bool
    {
        return PersonalDashboardModel::query()
            ->where('user_id', auth()->id())
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    private function uniqueDashboardName(string $base): string
    {
        $base = Str::limit(trim($base), 72, '');
        $candidate = $base;
        $number = 2;

        while ($this->dashboardNameExists($candidate)) {
            $candidate = Str::limit($base, 70, '') . ' ' . $number++;
        }

        return $candidate;
    }

    private function isAllowedQuickLinkUrl(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > 2048) {
            return false;
        }

        if (Str::startsWith($url, '/') && ! Str::startsWith($url, '//')) {
            return true;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function notifyError(string $message): void
    {
        Notification::make()
            ->danger()
            ->title('No se pudo completar la acción')
            ->body($message)
            ->send();
    }
}
