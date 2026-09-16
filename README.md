# Param FieldTrack

Independent attendance and employee route-tracking application (Web + Android).

This project reuses Punch In / Punch Out / GPS / photo / background route-tracking from ParamGold Sales ERP, with hierarchy:

Admin → Director → Project Head → Center Manager → Employee

ParamGold Sales ERP is **not** modified. FieldTrack uses its own database, API, users, and Android package (`com.param.fieldtrack`).

## Local setup (Backend)

```bash
cd Backend
copy .env.example .env
```

Then set local values in `.env`:

```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Web admin: http://127.0.0.1:8000/admin

Local seeded logins (not production database credentials):

| Role | Login ID | Password |
|---|---|---|
| Admin | director | Director@123 |
| Director | fielddirector | Director@123 |
| Project Head | projecthead | ProjectHead@123 |
| Center Manager | centermgr | CenterMgr@123 |
| Employee | 9876543210 | Employee@123 |

Production seeding creates **only** the Admin account (existing `director` login). Change that password immediately after first live login.

## Local setup (Mobile)

Default API URL is `https://fieldtrack.paramsocialfoundation.org/api`.

```bash
cd Mobile
flutter pub get
flutter analyze
flutter build apk --release
```

## Live hosting requirements

- **Subdomain:** `fieldtrack.paramgold.in` (independent from `erp.paramgold.in`)
- **Document root:** `Backend/public`
- **Database:** a new empty MySQL database. Do not use the ParamGold ERP database.
- **PHP:** 8.2+
- **Permissions:** `Backend/storage` and `Backend/bootstrap/cache` must be writable
- **Storage link:** `php artisan storage:link` so punch photos are public at `/storage/...`
- **Queue / scheduler:** not required. Route points are saved by the mobile app; `QUEUE_CONNECTION=sync` is enough.
- **Mobile API URL:** `https://fieldtrack.paramsocialfoundation.org/api` (compiled into the APK)

## Production server commands

On the live server, after Git clone and filling `Backend/.env` from `.env.example`:

```bash
cd Backend
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Then rebuild the Android APK so it uses the live API URL.
