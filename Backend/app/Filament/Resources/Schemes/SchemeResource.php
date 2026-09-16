<?php

namespace App\Filament\Resources\Schemes;

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
    protected static ?string $model = Scheme::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Admissions';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Scheme Master';

    protected static ?string $modelLabel = 'Scheme';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageSchemes($user));
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
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('admissions_count')->counts('admissions')->label('Admissions'),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
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
