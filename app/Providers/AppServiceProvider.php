<?php

namespace App\Providers;

use App\Policies\RolePolicy;
use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RichEditor::configureUsing(
            fn (RichEditor $editor): RichEditor => $editor
                ->enableToolbarButtons(['textColor']),
        );

        Gate::policy(Role::class, RolePolicy::class);
    }
}
