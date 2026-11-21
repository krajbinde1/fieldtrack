<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Models\Employee;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListOrgUsers extends ListRecords
{
    protected static string $resource = OrgUserResource::class;

    #[Url]
    public int $employees = 0;

    #[Url]
    public int $center = 0;

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->centerEmployeeQuery() !== null) {
            return 'Employees for the selected center.';
        }

        return 'Manage organization user accounts and roles for your authorized scope.';
    }

    protected function getHeaderActions(): array
    {
        if ($this->centerEmployeeQuery() !== null) {
            return [];
        }

        return [CreateAction::make()];
    }

    protected function getTableQuery(): Builder
    {
        return $this->centerEmployeeQuery() ?? parent::getTableQuery();
    }

    protected function makeTable(): Table
    {
        $table = parent::makeTable();

        if ($this->centerEmployeeQuery() === null) {
            return $table;
        }

        return $table
            ->columns([
                TextColumn::make('employee_code')->label('Employee Code')->searchable(),
                TextColumn::make('full_name')->label('Name')->searchable(),
                TextColumn::make('designation')->label('Designation')->placeholder('—'),
                TextColumn::make('mobile')->label('Mobile')->searchable(),
                TextColumn::make('center.name')->label('Center')->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
            ])
            ->filters([])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Employee $record): string => OrgUserResource::getUrl('viewEmployee', [
                        'employee' => $record,
                        'center' => $this->center,
                    ])),
            ])
            ->recordAction(null)
            ->recordUrl(fn (Employee $record): string => OrgUserResource::getUrl('viewEmployee', [
                'employee' => $record,
                'center' => $this->center,
            ]));
    }

    private function centerEmployeeQuery(): ?Builder
    {
        $user = auth()->user();

        if (! $user instanceof User || $this->employees !== 1 || ! $this->centerIsAuthorized($user)) {
            return null;
        }

        return OrgUserResource::centerEmployeesQuery($user, $this->center);
    }

    private function centerIsAuthorized(User $user): bool
    {
        if ($this->center < 1) {
            return false;
        }

        if ($user->isAdmin()) {
            $selected = app(AdminCenterContext::class)->idFor($user);

            if ($selected === null || $selected !== $this->center) {
                return false;
            }
        }

        return app(OrganizationAccessService::class)
            ->centerQuery($user)
            ->whereKey($this->center)
            ->exists();
    }
}
