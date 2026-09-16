<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile APK version (in-app update)
    |--------------------------------------------------------------------------
    |
    | Fallback for GET /api/app-version until Admin saves App Update Settings.
    | After a row exists in mobile_app_settings, the API uses the database.
    |
    | latest_build is the source of truth. Increment it for every APK you
    | upload to /apk/paramfieldtrack-latest.apk.
    |
    | After each release:
    | 1. Bump Flutter pubspec.yaml version AND +build number
    | 2. Build app-release.apk
    | 3. Admin Web → App Update Settings → upload APK, save version + build
    |
    | latest_build must match the uploaded APK's build, or installed copies
    | of that APK will keep seeing themselves as outdated.
    |
    */

    'latest_version' => env('MOBILE_APP_LATEST_VERSION', '1.0.4'),

    'latest_build' => (int) env('MOBILE_APP_LATEST_BUILD', 5),

    'apk_url' => env('MOBILE_APP_APK_URL'),

    'force_update' => filter_var(env('MOBILE_APP_FORCE_UPDATE', false), FILTER_VALIDATE_BOOLEAN),

    'message' => env(
        'MOBILE_APP_UPDATE_MESSAGE',
        'A new version of Param FieldTrack is available. Please update to continue.',
    ),

];
