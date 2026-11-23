#Requires -Version 5.1
<#
    Builds a release APK. The version name stays as written in pubspec.yaml.
    The build number after + is increased by one before the build starts.
#>
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$root = $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($root)) {
    $root = Split-Path -Parent $MyInvocation.MyCommand.Path
}
Set-Location $root

$pubspecPath = Join-Path $root 'pubspec.yaml'
if (-not (Test-Path -LiteralPath $pubspecPath)) {
    throw "pubspec.yaml was not found in $root"
}

if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
    throw 'flutter is not on PATH.'
}

$mutex = New-Object System.Threading.Mutex($false, 'Local\ParamFieldTrackReleaseApk')
$locked = $false
try {
    try {
        $locked = $mutex.WaitOne([TimeSpan]::FromMinutes(30))
    }
    catch [System.Threading.AbandonedMutexException] {
        $locked = $true
    }
    if (-not $locked) {
        throw 'Another release APK build is still running. Wait for it to finish so the build number stays unique.'
    }

    $utf8 = New-Object System.Text.UTF8Encoding $false
    $text = [System.IO.File]::ReadAllText($pubspecPath)
    $matches = [regex]::Matches($text, '(?m)^version:\s*(?<name>\d+\.\d+\.\d+)\+(?<build>\d+)\s*$')
    if ($matches.Count -ne 1) {
        throw 'pubspec.yaml must contain exactly one line like version: 1.0.7+8.'
    }

    $versionName = $matches[0].Groups['name'].Value
    $currentBuild = [int]$matches[0].Groups['build'].Value
    $newBuild = $currentBuild + 1
    if ($newBuild -le $currentBuild) {
        throw "Refusing to reuse build number $currentBuild."
    }

    $updated = [regex]::Replace(
        $text,
        '(?m)^version:\s*\d+\.\d+\.\d+\+\d+\s*$',
        "version: $versionName+$newBuild",
        1)
    $confirmed = [regex]::Matches($updated, '(?m)^version:\s*(?<name>\d+\.\d+\.\d+)\+(?<build>\d+)\s*$')
    if ($confirmed.Count -ne 1 -or $confirmed[0].Groups['name'].Value -ne $versionName -or [int]$confirmed[0].Groups['build'].Value -ne $newBuild) {
        throw 'The build number in pubspec.yaml was not updated uniquely.'
    }

    $temporary = "$pubspecPath.tmp"
    [System.IO.File]::WriteAllText($temporary, $updated, $utf8)
    Move-Item -LiteralPath $temporary -Destination $pubspecPath -Force

    $written = [regex]::Match(
        [System.IO.File]::ReadAllText($pubspecPath),
        '(?m)^version:\s*(?<name>\d+\.\d+\.\d+)\+(?<build>\d+)\s*$')
    if (-not $written.Success -or $written.Groups['name'].Value -ne $versionName -or [int]$written.Groups['build'].Value -ne $newBuild) {
        throw 'pubspec.yaml does not contain the new build number. The APK was not built.'
    }

    Write-Host ""
    Write-Host "Version name: $versionName"
    Write-Host "Build number: $newBuild"
    Write-Host ""
    Write-Host "Building release APK..."

    & flutter build apk --release --build-name=$versionName --build-number=$newBuild
    if ($LASTEXITCODE -ne 0) {
        throw "flutter build apk failed with exit code $LASTEXITCODE. Build number $newBuild is reserved in pubspec.yaml."
    }

    $apk = Join-Path $root 'build\app\outputs\flutter-apk\app-release.apk'
    if (-not (Test-Path -LiteralPath $apk)) {
        throw "The build finished, but the APK was not found at $apk"
    }

    Write-Host ""
    Write-Host "Version name: $versionName"
    Write-Host "Build number: $newBuild"
    Write-Host "APK: $apk"
    exit 0
}
finally {
    if ($locked) {
        $mutex.ReleaseMutex() | Out-Null
    }
    $mutex.Dispose()
}
