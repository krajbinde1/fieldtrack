<?php

use App\Enums\UserRole;
use App\Filament\Pages\AppUpdateSettings;
use App\Models\MobileAppSetting;
use App\Models\User;
use App\Services\MobileApp\MobileAppVersionService;
use Livewire\Livewire;

it('returns the public mobile app version payload from config when no settings row exists', function () {
    config([
        'app.url' => 'https://fieldtrack.paramsocialfoundation.org',
        'mobile_app.latest_version' => '1.0.4',
        'mobile_app.latest_build' => 5,
        'mobile_app.apk_url' => 'https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk',
        'mobile_app.force_update' => false,
        'mobile_app.message' => 'A new version of Param FieldTrack is available. Please update to continue.',
    ]);

    expect(MobileAppSetting::query()->count())->toBe(0);

    $this->getJson('/api/app-version')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('latest_version', '1.0.4')
        ->assertJsonPath('latest_build', 5)
        ->assertJsonPath('apk_url', 'https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk')
        ->assertJsonPath('force_update', false)
        ->assertJsonPath('message', 'A new version of Param FieldTrack is available. Please update to continue.');
});

it('does not require authentication', function () {
    $this->getJson('/api/app-version')->assertOk();
});

it('prefers database settings over config once a row exists', function () {
    config([
        'mobile_app.latest_version' => '1.0.0',
        'mobile_app.latest_build' => 2,
        'mobile_app.apk_url' => 'https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk',
        'mobile_app.force_update' => false,
        'mobile_app.message' => 'config fallback',
    ]);

    MobileAppSetting::query()->create([
        'latest_version' => '1.0.5',
        'latest_build' => 6,
        'force_update' => true,
        'apk_url' => 'https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk',
        'update_message' => 'A new version of Param FieldTrack is available. Please update to continue.',
    ]);

    $this->getJson('/api/app-version')
        ->assertOk()
        ->assertJsonPath('latest_version', '1.0.5')
        ->assertJsonPath('latest_build', 6)
        ->assertJsonPath('force_update', true)
        ->assertJsonPath('message', 'A new version of Param FieldTrack is available. Please update to continue.');
});

it('updates the single settings row instead of inserting duplicates', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin->value,
    ]);

    $service = app(MobileAppVersionService::class);

    $service->save([
        'latest_version' => '1.0.5',
        'latest_build' => 6,
        'force_update' => true,
        'apk_url' => 'https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk',
        'update_message' => 'Update available.',
    ], $admin);

    $service->save([
        'latest_version' => '1.0.6',
        'latest_build' => 7,
        'force_update' => false,
        'apk_url' => 'https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk',
        'update_message' => 'Please update.',
    ], $admin);

    expect(MobileAppSetting::query()->count())->toBe(1)
        ->and(MobileAppSetting::query()->first()?->latest_build)->toBe(7)
        ->and(MobileAppSetting::query()->first()?->force_update)->toBeFalse()
        ->and(MobileAppSetting::query()->first()?->updated_by)->toBe($admin->id);
});

it('does not allow latest build to be lowered', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin->value,
    ]);

    MobileAppSetting::query()->create([
        'latest_version' => '1.0.5',
        'latest_build' => 6,
        'force_update' => true,
        'apk_url' => 'https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk',
        'update_message' => 'Please update.',
        'updated_by' => $admin->id,
    ]);

    expect(fn () => app(MobileAppVersionService::class)->save([
        'latest_version' => '1.0.4',
        'latest_build' => 5,
        'force_update' => true,
        'apk_url' => 'https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk',
        'update_message' => 'Please update.',
    ], $admin))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(MobileAppSetting::query()->first()?->latest_build)->toBe(6);
});

it('stores the uploaded apk at the fixed public path and sets apk url', function () {
    config(['app.url' => 'https://fieldtrack.paramsocialfoundation.org']);

    $admin = User::factory()->create([
        'role' => UserRole::Admin->value,
    ]);

    $source = sys_get_temp_dir().DIRECTORY_SEPARATOR.'paramfieldtrack-upload-'.uniqid().'.apk';
    file_put_contents($source, str_repeat('A', 2048));

    $destination = app(MobileAppVersionService::class)->latestApkAbsolutePath();
    $directory = dirname($destination);
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    if (is_file($destination)) {
        unlink($destination);
    }

    try {
        $saved = app(MobileAppVersionService::class)->save([
            'latest_version' => '1.0.5',
            'latest_build' => 6,
            'force_update' => true,
            'apk_url' => '',
            'update_message' => 'Please update.',
        ], $admin, $source);

        expect($saved->apk_url)->toBe('https://fieldtrack.paramsocialfoundation.org/apk/paramfieldtrack-latest.apk')
            ->and(is_file($destination))->toBeTrue()
            ->and(filesize($destination))->toBe(2048);

        $this->get('/apk/paramfieldtrack-latest.apk')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="paramfieldtrack-latest.apk"');
    } finally {
        @unlink($source);
        @unlink($destination);
    }
});

it('returns 404 for the apk download when no file has been uploaded', function () {
    $destination = app(MobileAppVersionService::class)->latestApkAbsolutePath();
    if (is_file($destination)) {
        unlink($destination);
    }

    $this->get('/apk/paramfieldtrack-latest.apk')->assertNotFound();
});

it('allows only admin users to open app update settings', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin->value,
    ]);

    $director = User::factory()->create([
        'role' => UserRole::Director->value,
    ]);

    $manager = User::factory()->create([
        'role' => UserRole::CenterManager->value,
    ]);

    Livewire::actingAs($admin)
        ->test(AppUpdateSettings::class)
        ->assertSuccessful();

    Livewire::actingAs($director)
        ->test(AppUpdateSettings::class)
        ->assertForbidden();

    Livewire::actingAs($manager)
        ->test(AppUpdateSettings::class)
        ->assertForbidden();
});
