<?php

use App\Support\PublicStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

it('builds a public storage url from the stored attendance photo path', function () {
    expect(PublicStorage::relativePath('attendance/in.jpg'))->toBe('attendance/in.jpg')
        ->and(PublicStorage::relativePath('\\attendance\\in.jpg'))->toBe('attendance/in.jpg')
        ->and(PublicStorage::relativePath('storage/attendance/in.jpg'))->toBe('attendance/in.jpg')
        ->and(PublicStorage::url('attendance/in.jpg'))->toEndWith('/storage/attendance/in.jpg');
});

it('builds production https punch photo urls from the public app url', function () {
    URL::forceScheme('https');
    URL::forceRootUrl('https://fieldtrack.paramsocialfoundation.org');

    expect(PublicStorage::url('attendance/in.jpg'))
        ->toBe('https://fieldtrack.paramsocialfoundation.org/storage/attendance/in.jpg');
});

it('serves punch photos from storage without a public symlink', function () {
    Storage::fake('public');
    Storage::disk('public')->put('attendance/in.jpg', 'jpeg-bytes');

    $this->get('/storage/attendance/in.jpg')->assertOk();
    $this->get('/storage/admissions/secret.jpg')->assertNotFound();
    $this->get('/storage/../.env')->assertNotFound();
});
