<?php

namespace App\Filament\Resources\Schemes;

use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Resources\Schemes\Pages\CreateScheme;
use App\Filament\Resources\Schemes\Pages\EditScheme;
use App\Filament\Resources\Schemes\Pages\ListSchemes;
use App\Models\Scheme;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SchemeResource extends Resource
{
    use ScopesRecordsByOrganization;

    protected static ?string $model = Scheme::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Scheme Master';

    protected static ?string $modelLabel = 'Scheme';

    protected static ?string $pluralModelLabel = 'Schemes';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isDirector() || $user?->isProjectHead());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageSchemes($user));
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageSchemes($user));
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('code')->maxLength(64)->unique(ignoreRecord: true),
            Textarea::make('description')->columnSpanFull(),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('centers_count')->counts('centers')->label('Centers'),
                TextColumn::make('admissions_count')->counts('admissions')->label('Admissions'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchemes::route('/'),
            'create' => CreateScheme::route('/create'),
            'edit' => EditScheme::route('/{record}/edit'),
        ];
    }
}
