<?php

namespace App\Filament\Pages;

use App\Models\Center;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SelectCenter extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Select Center';

    protected static ?string $slug = 'select-center';

    protected string $view = 'filament.pages.select-center';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isAdmin() || $user->isCenterCoordinator());
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $selected = app(AdminCenterContext::class)->idFor(auth()->user());

        $this->form->fill([
            'center_id' => $selected,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $current = app(AdminCenterContext::class)->centerFor(auth()->user());

        return $schema
            ->components([
                Section::make('Select Center')
                    ->description($current instanceof Center
                        ? 'You are working in '.$current->name.'. Choose another authorized center to switch.'
                        : 'Choose an authorized center to open its dashboard. The main dashboard keeps every authorized center until you do.')
                    ->schema([
                        Select::make('center_id')
                            ->label('Center')
                            ->options(function (): array {
                                $user = auth()->user();
                                if (! $user instanceof User) {
                                    return [];
                                }

                                return app(OrganizationAccessService::class)
                                    ->centerQuery($user)
                                    ->with('scheme:id,name')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Center $center): array => [
                                        $center->id => $center->assignmentLabel(),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        abort_unless(static::canAccess(), 403);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->form->getState();

        app(AdminCenterContext::class)->select($user, (int) ($data['center_id'] ?? 0));

        return redirect()->intended(CenterDashboard::getUrl());
    }
}
