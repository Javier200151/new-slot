<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Configuration extends Cluster
{
    protected static string | UnitEnum | null $navigationGroup = 'Actividades';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Configuración actividades';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configuración actividades';

    protected static ?string $clusterBreadcrumb = 'Configuración actividades';
}
