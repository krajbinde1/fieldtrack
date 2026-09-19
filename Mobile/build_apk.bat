@echo off
setlocal EnableExtensions
cd /d "%~dp0"

if not exist "pubspec.yaml" (
  echo ERROR: pubspec.yaml not found in %CD%
  exit /b 1
)

where flutter >nul 2>&1
if errorlevel 1 (
  echo ERROR: flutter is not on PATH.
  exit /b 1
)

set "VERSION_FILE=%TEMP%\fieldtrack_apk_version.txt"
del "%VERSION_FILE%" >nul 2>&1

powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $path='%~dp0pubspec.yaml'; $out=$env:TEMP+'\fieldtrack_apk_version.txt'; $text=[IO.File]::ReadAllText($path); $m=[regex]::Match($text,'(?m)^version:\s*(\d+)\.(\d+)\.(\d+)\+(\d+)\s*$'); if(-not $m.Success){ throw 'Could not read version/build from pubspec.yaml (expected 1.0.4+5).' }; $major=[int]$m.Groups[1].Value; $minor=[int]$m.Groups[2].Value; $patch=[int]$m.Groups[3].Value+1; $build=[int]$m.Groups[4].Value+1; $version=('{0}.{1}.{2}' -f $major,$minor,$patch); $full=('{0}+{1}' -f $version,$build); $updated=[regex]::Replace($text,'(?m)^version:\s*\d+\.\d+\.\d+\+\d+\s*$',('version: '+$full),1); $utf8=New-Object System.Text.UTF8Encoding $false; [IO.File]::WriteAllText($path,$updated,$utf8); [IO.File]::WriteAllText($out,('version='+$version+[Environment]::NewLine+'build='+$build),$utf8)"
if errorlevel 1 (
  echo ERROR: Failed to increment version in pubspec.yaml
  exit /b 1
)

set "NEW_VERSION="
set "NEW_BUILD="
for /f "usebackq tokens=1,2 delims==" %%A in ("%VERSION_FILE%") do (
  if /I "%%A"=="version" set "NEW_VERSION=%%B"
  if /I "%%A"=="build" set "NEW_BUILD=%%B"
)
del "%VERSION_FILE%" >nul 2>&1

if not defined NEW_VERSION (
  echo ERROR: Could not determine the new version.
  exit /b 1
)
if not defined NEW_BUILD (
  echo ERROR: Could not determine the new build number.
  exit /b 1
)

echo.
echo Version: %NEW_VERSION%
echo Build Number: %NEW_BUILD%
echo.

echo Building app-release.apk with Version %NEW_VERSION% / Build %NEW_BUILD% ...
call flutter build apk --release --build-name=%NEW_VERSION% --build-number=%NEW_BUILD%
if errorlevel 1 (
  echo ERROR: APK build failed.
  exit /b 1
)

echo.
echo Built: %CD%\build\app\outputs\flutter-apk\app-release.apk
echo Version: %NEW_VERSION%
echo Build Number: %NEW_BUILD%
exit /b 0
