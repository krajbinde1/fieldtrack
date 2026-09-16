<?php

namespace App\Services\MobileApp;

use App\Models\MobileAppSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileAppVersionService
{
    public const LATEST_APK_PUBLIC_PATH = 'apk/paramfieldtrack-latest.apk';

    public const DEFAULT_APK_URL = 'https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk';

    public const DEFAULT_MESSAGE = 'A new version of Param FieldTrack is available. Please update to continue.';

    /**
     * @return array{
     *     latest_version: string,
     *     latest_build: int,
     *     apk_url: string,
     *     force_update: bool,
     *     message: string,
     *     source: 'database'|'config',
     *     updated_at: ?string,
     *     updated_by_name: ?string,
     *     apk_file_ready: bool
     * }
     */
    public function current(): array
    {
        $row = $this->activeRecord();

        if ($row !== null) {
            return $this->fromModel($row);
        }

        return $this->fromConfig();
    }

    public function defaultApkUrl(): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '' && preg_match('/^https:\/\//i', $appUrl) === 1) {
            return $appUrl.'/'.self::LATEST_APK_PUBLIC_PATH;
        }

        return self::DEFAULT_APK_URL;
    }

    public function latestApkAbsolutePath(): string
    {
        return public_path(self::LATEST_APK_PUBLIC_PATH);
    }

    public function apkFileReady(): bool
    {
        $path = $this->latestApkAbsolutePath();

        return is_file($path) && is_readable($path) && filesize($path) > 1024;
    }

    /**
     * Copy an uploaded APK to the fixed public path and return the download URL.
     */
    public function storeLatestApk(string $sourcePath): string
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw ValidationException::withMessages([
                'apk_file' => 'The uploaded APK could not be read.',
            ]);
        }

        $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
        if ($extension !== '' && $extension !== 'apk') {
            throw ValidationException::withMessages([
                'apk_file' => 'Only .apk files can be uploaded.',
            ]);
        }

        $destination = $this->latestApkAbsolutePath();
        $directory = dirname($destination);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw ValidationException::withMessages([
                'apk_file' => 'Unable to create the APK storage directory.',
            ]);
        }

        if (! copy($sourcePath, $destination)) {
            throw ValidationException::withMessages([
                'apk_file' => 'Unable to store the APK on the server.',
            ]);
        }

        @chmod($destination, 0644);

        return $this->defaultApkUrl();
    }

    /**
     * @param  array{
     *     latest_version: string,
     *     latest_build: int|string,
     *     force_update: bool,
     *     apk_url?: string,
     *     update_message?: ?string
     * }  $data
     */
    public function save(array $data, User $actor, ?string $uploadedApkPath = null): MobileAppSetting
    {
        $latestVersion = trim((string) ($data['latest_version'] ?? ''));
        $latestBuild = (int) ($data['latest_build'] ?? 0);
        $apkUrl = trim((string) ($data['apk_url'] ?? ''));
        $forceUpdate = (bool) ($data['force_update'] ?? false);
        $updateMessage = trim((string) ($data['update_message'] ?? ''));

        if ($uploadedApkPath !== null && $uploadedApkPath !== '') {
            $apkUrl = $this->storeLatestApk($uploadedApkPath);
        }

        if ($apkUrl === '') {
            $apkUrl = $this->defaultApkUrl();
        }

        $this->assertValidPayload($latestVersion, $latestBuild, $apkUrl);

        return DB::transaction(function () use ($latestVersion, $latestBuild, $apkUrl, $forceUpdate, $updateMessage, $actor): MobileAppSetting {
            $row = MobileAppSetting::query()->lockForUpdate()->orderBy('id')->first();
            $currentBuild = $row?->latest_build ?? $this->fromConfig()['latest_build'];

            if ($latestBuild < $currentBuild) {
                throw ValidationException::withMessages([
                    'latest_build' => "Latest Build cannot be lower than the currently published build ({$currentBuild}). Lowering it can skip required updates.",
                ]);
            }

            if ($row === null) {
                $row = new MobileAppSetting;
            }

            $row->latest_version = $latestVersion;
            $row->latest_build = $latestBuild;
            $row->force_update = $forceUpdate;
            $row->apk_url = $apkUrl;
            $row->update_message = $updateMessage !== '' ? $updateMessage : null;
            $row->updated_by = $actor->id;
            $row->save();

            return $row->refresh()->load('updatedByUser');
        });
    }

    public function activeRecord(): ?MobileAppSetting
    {
        return MobileAppSetting::query()
            ->with('updatedByUser')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{
     *     latest_version: string,
     *     latest_build: int,
     *     apk_url: string,
     *     force_update: bool,
     *     message: string,
     *     source: 'database',
     *     updated_at: ?string,
     *     updated_by_name: ?string,
     *     apk_file_ready: bool
     * }
     */
    private function fromModel(MobileAppSetting $row): array
    {
        $apkUrl = trim((string) $row->apk_url);
        if ($apkUrl === '') {
            $apkUrl = $this->defaultApkUrl();
        }

        $message = trim((string) $row->update_message);
        if ($message === '') {
            $message = self::DEFAULT_MESSAGE;
        }

        return [
            'latest_version' => (string) $row->latest_version,
            'latest_build' => (int) $row->latest_build,
            'apk_url' => $apkUrl,
            'force_update' => (bool) $row->force_update,
            'message' => $message,
            'source' => 'database',
            'updated_at' => $row->updated_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
            'updated_by_name' => $row->updatedByUser?->name,
            'apk_file_ready' => $this->apkFileReady(),
        ];
    }

    /**
     * @return array{
     *     latest_version: string,
     *     latest_build: int,
     *     apk_url: string,
     *     force_update: bool,
     *     message: string,
     *     source: 'config',
     *     updated_at: null,
     *     updated_by_name: null,
     *     apk_file_ready: bool
     * }
     */
    private function fromConfig(): array
    {
        $apkUrl = trim((string) config('mobile_app.apk_url', ''));
        if ($apkUrl === '') {
            $apkUrl = $this->defaultApkUrl();
        }

        $message = trim((string) config('mobile_app.message', self::DEFAULT_MESSAGE));
        if ($message === '') {
            $message = self::DEFAULT_MESSAGE;
        }

        return [
            'latest_version' => (string) config('mobile_app.latest_version', '1.0.4'),
            'latest_build' => (int) config('mobile_app.latest_build', 5),
            'apk_url' => $apkUrl,
            'force_update' => (bool) config('mobile_app.force_update', false),
            'message' => $message,
            'source' => 'config',
            'updated_at' => null,
            'updated_by_name' => null,
            'apk_file_ready' => $this->apkFileReady(),
        ];
    }

    private function assertValidPayload(string $latestVersion, int $latestBuild, string $apkUrl): void
    {
        $errors = [];

        if ($latestVersion === '') {
            $errors['latest_version'] = 'Latest Version is required.';
        }

        if ($latestBuild < 1) {
            $errors['latest_build'] = 'Latest Build must be a positive integer.';
        }

        $https = preg_match('/^https:\/\//i', $apkUrl) === 1;
        $httpLocal = app()->environment(['local', 'testing']) && preg_match('/^http:\/\//i', $apkUrl) === 1;

        if ($apkUrl === '' || (! $https && ! $httpLocal) || filter_var($apkUrl, FILTER_VALIDATE_URL) === false) {
            $errors['apk_url'] = 'APK URL must be a valid HTTPS URL.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
