<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class EventConfiguration extends Cluster
{
    protected static string | UnitEnum | null $navigationGroup = 'Eventos';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Configuración eventos';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configuración eventos';

    protected static ?string $clusterBreadcrumb = 'Configuración eventos';
}
