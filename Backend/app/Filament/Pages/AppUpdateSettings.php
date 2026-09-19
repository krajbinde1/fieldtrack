<?php

namespace App\Filament\Pages;

use App\Services\MobileApp\MobileAppVersionService;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AppUpdateSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'App Update Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $title = 'App Update Settings';

    protected static ?string $slug = 'app-update-settings';

    protected string $view = 'filament.pages.app-update-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * @var array{
     *     latest_version: string,
     *     latest_build: int,
     *     apk_url: string,
     *     force_update: bool,
     *     message: string,
     *     source: string,
     *     updated_at: ?string,
     *     updated_by_name: ?string,
     *     apk_file_ready: bool
     * }
     */
    public array $currentSettings = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->refreshCurrentSettings();
        $this->form->fill([
            'latest_version' => $this->currentSettings['latest_version'],
            'latest_build' => $this->currentSettings['latest_build'],
            'force_update' => $this->currentSettings['force_update'],
            'apk_url' => $this->currentSettings['apk_url'],
            'update_message' => $this->currentSettings['message'],
            'apk_file' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mobile app version')
                    ->description('These values are served by GET /api/app-version. The app compares the installed build number against Latest Build Number.')
                    ->schema([
                        TextInput::make('latest_version')
                            ->label('Latest Version')
                            ->required()
                            ->maxLength(32)
                            ->placeholder('1.0.5'),
                        TextInput::make('latest_build')
                            ->label('Latest Build Number')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->helperText(fn (): string => 'Must match the uploaded APK +build number from pubspec.yaml. Cannot be lower than '.$this->currentSettings['latest_build'].'.')
                            ->rule(function (): \Closure {
                                return function (string $attribute, mixed $value, \Closure $fail): void {
                                    $currentBuild = (int) ($this->currentSettings['latest_build'] ?? 0);
                                    if ((int) $value < $currentBuild) {
                                        $fail("Latest Build cannot be lower than the currently published build ({$currentBuild}). Lowering it can skip required updates.");
                                    }
                                };
                            }),
                        Toggle::make('force_update')
                            ->label('Force Update')
                            ->helperText('ON: users cannot continue until they update. OFF: users can tap Skip / Later.')
                            ->default(false)
                            ->inline(false),
                        FileUpload::make('apk_file')
                            ->label('Upload / Replace APK')
                            ->acceptedFileTypes([
                                'application/vnd.android.package-archive',
                                'application/octet-stream',
                                'application/zip',
                                'application/java-archive',
                            ])
                            ->maxSize(122880)
                            ->disk('public')
                            ->directory('apk-uploads')
                            ->visibility('private')
                            ->downloadable(false)
                            ->openable(false)
                            ->dehydrated(false)
                            ->required(fn (): bool => ! ($this->currentSettings['apk_file_ready'] ?? false))
                            ->helperText('Replaces the public file at /apk/paramfieldtrack-latest.apk. Leave empty only when that file is already on the server.'),
                        TextInput::make('apk_url')
                            ->label('APK URL')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Always https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk'),
                        Textarea::make('update_message')
                            ->label('Update Message')
                            ->rows(3)
                            ->maxLength(2000)
                            ->placeholder(MobileAppVersionService::DEFAULT_MESSAGE),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $state = $this->form->getState();
            $uploadedPath = $this->resolveUploadedApkPath($this->data['apk_file'] ?? null);
            unset($state['apk_file'], $state['apk_url']);

            app(MobileAppVersionService::class)->save($state, auth()->user(), $uploadedPath);
            $this->forgetStagedApkUpload($this->data['apk_file'] ?? null);
        } catch (ValidationException $e) {
            throw $e;
        }

        $this->refreshCurrentSettings();
        $this->form->fill([
            'latest_version' => $this->currentSettings['latest_version'],
            'latest_build' => $this->currentSettings['latest_build'],
            'force_update' => $this->currentSettings['force_update'],
            'apk_url' => $this->currentSettings['apk_url'],
            'update_message' => $this->currentSettings['message'],
            'apk_file' => null,
        ]);

        Notification::make()
            ->title('App update settings saved')
            ->body('GET /api/app-version now returns these values. The APK is at /apk/paramfieldtrack-latest.apk.')
            ->success()
            ->send();
    }

    public function usesConfigFallback(): bool
    {
        return ($this->currentSettings['source'] ?? 'config') === 'config';
    }

    private function refreshCurrentSettings(): void
    {
        $this->currentSettings = app(MobileAppVersionService::class)->current();
    }

    private function resolveUploadedApkPath(mixed $uploaded): ?string
    {
        if ($uploaded instanceof TemporaryUploadedFile) {
            $path = $uploaded->getRealPath() ?: $uploaded->getPathname();
            if (is_string($path) && is_file($path)) {
                return $path;
            }

            return $this->resolveUploadedApkPath($uploaded->getFilename());
        }

        if (is_array($uploaded)) {
            foreach ($uploaded as $item) {
                $resolved = $this->resolveUploadedApkPath($item);
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            return null;
        }

        if (! is_string($uploaded) || $uploaded === '') {
            return null;
        }

        if (is_file($uploaded)) {
            return $uploaded;
        }

        foreach (['public', 'local'] as $disk) {
            if (! Storage::disk($disk)->exists($uploaded)) {
                continue;
            }

            $fromDisk = Storage::disk($disk)->path($uploaded);
            if (is_file($fromDisk)) {
                return $fromDisk;
            }
        }

        return null;
    }

    private function forgetStagedApkUpload(mixed $uploaded): void
    {
        if (is_array($uploaded)) {
            foreach ($uploaded as $item) {
                $this->forgetStagedApkUpload($item);
            }

            return;
        }

        if (! is_string($uploaded) || $uploaded === '' || ! str_starts_with(str_replace('\\', '/', $uploaded), 'apk-uploads/')) {
            return;
        }

        Storage::disk('public')->delete($uploaded);
        Storage::disk('local')->delete($uploaded);
    }
}
