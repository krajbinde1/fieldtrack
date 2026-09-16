<?php

namespace App\Http\Controllers;

use App\Services\MobileApp\MobileAppVersionService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LatestApkController extends Controller
{
    public function __invoke(MobileAppVersionService $settings): BinaryFileResponse
    {
        $path = $settings->latestApkAbsolutePath();
        abort_unless(is_file($path), 404, 'Latest APK has not been uploaded yet.');

        return response()->file($path, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="paramfieldtrack-latest.apk"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
