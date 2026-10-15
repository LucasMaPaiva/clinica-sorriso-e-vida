<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = 'dashboard';

    protected static ?string $title = 'Visão geral';

    protected static ?string $navigationLabel = 'Visão geral';

    protected static ?int $navigationSort = -25;
}
