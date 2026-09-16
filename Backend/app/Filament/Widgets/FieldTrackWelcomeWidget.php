<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class FieldTrackWelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.fieldtrack-welcome-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function getHeading(): ?string
    {
        $user = auth()->user();

        return 'Welcome, '.($user?->name ?? 'User');
    }

    public function getRoleLabel(): string
    {
        return auth()->user()?->roleEnum()->label() ?? '';
    }
}
