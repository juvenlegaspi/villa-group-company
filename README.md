# Villa Group Operations System

Laravel application for Villa Group division dashboards and operational workflows: Villa Shipping Lines, Yatira, JMV, Corporate, and HYVE.

## Requirements

- PHP 8.2 or newer with PDO MySQL, Mbstring, OpenSSL, Fileinfo, and GD
- MySQL 8
- Composer 2
- Node.js 20 or newer (only required when building Vite assets)

## Local setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

Configure the database, SMTP mailer, application URL, and a non-debug production environment in `.env`. Never commit `.env`.

## Production deployment

Use these minimum settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

Then deploy with:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

Run a queue worker under a process supervisor when queued jobs are introduced. Configure the Laravel scheduler to run every minute:

```cron
* * * * * cd /path/to/villa && php artisan schedule:run >> /dev/null 2>&1
```

Certificate expiry alerts run daily at 08:00 Asia/Manila and are deduplicated per certificate, recipient, and day.

Certificate reminders use SMTP email and optional Semaphore SMS. Configure Semaphore after obtaining the API key; the sender name may remain blank until the requested name is approved and set as the account default:

```dotenv
SEMAPHORE_ENABLED=true
SEMAPHORE_API_KEY=your-api-key
SEMAPHORE_SENDER_NAME=
SEMAPHORE_ENDPOINT=https://api.semaphore.co/api/v4/messages
```

When the custom sender becomes active, place its exact approved value in `SEMAPHORE_SENDER_NAME` and run `php artisan optimize:clear`. On Windows Server, create a Task Scheduler entry that runs `php artisan schedule:run` every minute from the project directory; on Linux use the cron entry above.

Villa Shipping checklist reminders run every minute in Asia/Manila. They are delivered to active Marine Operations Managers and the active Captain assigned to the event vessel. Delivery is deduplicated per event occurrence, recipient, and channel. A missed scheduler run is recovered within the configured catch-up window, and failed delivery is retried up to the configured limit:

```dotenv
SHIPPING_CALENDAR_CATCH_UP_MINUTES=1440
SHIPPING_CALENDAR_MAX_DELIVERY_ATTEMPTS=5
SHIPPING_CALENDAR_MAX_ATTACHMENTS=10
SHIPPING_CALENDAR_MAX_ATTACHMENT_BYTES=52428800
```

Monitor failed or exhausted delivery attempts in `shipping_calendar_reminder_logs`. Checklist attachments are stored on the private `local` disk and are available only through vessel-authorized download routes.

Password recovery sends a single-use link to the user's registered email. Set `APP_URL` to the public HTTPS address and configure a working SMTP mailer and sender address before deployment. Reset links expire after 60 minutes and requests are rate-limited.

## Access model

- Administrators/owners can access every division.
- Non-admin users are restricted to their assigned division.
- Vessel managers can manage shipping records; captains are restricted to assigned vessels.
- User administration and dry docking are administrator-only.
- Temporary passwords force a password change before module access.
- HYVE, Yatira, JMV, and Shipping routes enforce division access server-side.

## Storage

Certificate documents and voyage activity edit attachments are stored on Laravel's private `local` disk and downloaded only through authenticated, authorized controllers.

To migrate legacy public certificate files safely:

```bash
php artisan certificates:migrate-private
php artisan voyages:migrate-private-attachments
```

The command copies each file, verifies its SHA-256 checksum, updates certificate records, and only then removes the public copy. The `public/uploads` directory is ignored by Git. Existing repository history may still contain old documents; purge sensitive blobs from remote Git history separately if that repository has been shared.

## HYVE integration

This application reads the HYVE booking tables from the configured Villa database and serves local payment proofs from the sibling `../hyve/storage/app/public` directory. Deploy both applications with that directory relationship or replace it with a configured shared private filesystem before separating the services.

## Verification

```bash
php artisan test
php artisan route:list --except-vendor
php artisan schedule:list
php artisan migrate:status
php artisan view:cache
```

Do not deploy unless tests pass, no migrations are pending, `APP_DEBUG` is false, HTTPS cookies are enabled, SMTP is verified, and a database/private-storage backup has been tested.
