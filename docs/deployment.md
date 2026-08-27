# Deployment

This app is a standard Laravel 13 + Filament 5 app with one thing that makes
it not-quite-stateless: `RaiffeisenLoginJob` (`app/Jobs/RaiffeisenLoginJob.php`)
is dispatched to the queue and can legitimately run **180s+** while it waits
for the user to approve a mobile push 2FA prompt. Whatever you deploy to, the
queue worker's timeout must be set above that (240s is what local dev uses),
and `queue:listen` must never be used in place of `queue:work` — `queue:listen`
wraps each job in a child process with its own hard-coded 60s timeout that
ignores the job's own `$timeout` and will kill the login job mid-flow.

Required background processes, everywhere this app runs:

1. **PHP-FPM / web app** — serves HTTP requests (Filament admin panel + MCP
   routes).
2. **Queue worker** — `php artisan queue:work --tries=1 --timeout=240`,
   running continuously, auto-restarted on crash.
3. **Scheduler** — `php artisan schedule:run` once a minute, if/when
   `routes/console.php` grows scheduled tasks. Nothing is scheduled today, but
   wire it up anyway since adding a task later shouldn't require an infra
   change.

No horizon/redis queue driver is in use — `QUEUE_CONNECTION=database`,
`CACHE_STORE=database`, `SESSION_DRIVER=database` are all Postgres-backed, so
the only stateful dependency is the Postgres database. `storage:link` is not
required — the app doesn't currently serve anything off the `public` disk.

## Environment variables

Copy `.env.example` to `.env` and set at minimum:

- `APP_KEY` — generate with `php artisan key:generate` (never share this
  across environments; each deploy target gets its own).
- `APP_ENV=production`, `APP_DEBUG=false`
- `APP_URL` — the real public URL (Filament and Sanctum both derive
  redirect/cookie behavior from this).
- `DB_*` — Postgres connection.
- `SANCTUM_STATEFUL_DOMAINS` — only needed if something other than the app's
  own domain calls the API with cookies; the MCP token flow uses bearer
  tokens instead, so this can usually stay unset.
- `MAIL_*` — only relevant once/if the app sends mail; `log` driver is fine
  until then.

Since this is self-hosted for a household, there's no separate staging
environment to keep in sync — just `.env` on the one box.

## Release steps (any target)

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Restart the queue worker after every deploy (`php artisan queue:restart`
signals workers to finish their current job and exit; your process
supervisor must then bring them back up) — otherwise workers keep running
old code in memory.

Because Filament's admin panel enforces app-based 2FA
(`AdminPanelProvider::multiFactorAuthentication`, `isRequired: true`), the
first admin account should be created via
`php artisan make:admin` (interactive) rather than a seeder, so 2FA gets set
up properly at creation time.

After the first deploy (and after `merchant-categories:import` /
manual category edits change what should ship by default), seed the
merchant category rules — this does **not** create a user, so it's safe to
run in production, unlike the bare `db:seed` (which also runs
`UserFactory`, requiring the dev-only `fakerphp/faker` package that
`composer install --no-dev` doesn't install):

```sh
php artisan db:seed --class=MerchantCategorySeeder
php artisan transactions:recategorize
```

## Option A — LXC container (systemd)

Treat the container like a normal Linux box: PHP 8.3+, Postgres reachable
(either in the same container or a separate one/VM), nginx or Apache in
front of PHP-FPM, Node only needed at build time (front-end assets can be
built elsewhere and copied in, or built in the container and then Node
removed).

Two systemd units in addition to the web server:

`/etc/systemd/system/rai-stats-queue.service`:

```ini
[Unit]
Description=rai-stats queue worker
After=network.target postgresql.service

[Service]
User=www-data
WorkingDirectory=/var/www/rai-stats
ExecStart=/usr/bin/php artisan queue:work --tries=1 --timeout=240
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/rai-stats-scheduler.timer` + matching `.service`
(runs `schedule:run` every minute):

```ini
# rai-stats-scheduler.service
[Unit]
Description=rai-stats scheduler tick

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/var/www/rai-stats
ExecStart=/usr/bin/php artisan schedule:run
```

```ini
# rai-stats-scheduler.timer
[Unit]
Description=Run rai-stats scheduler every minute

[Timer]
OnCalendar=*-*-*-*-*:*
Persistent=true

[Install]
WantedBy=timers.target
```

```sh
sudo systemctl daemon-reload
sudo systemctl enable --now rai-stats-queue.service
sudo systemctl enable --now rai-stats-scheduler.timer
```

After a deploy: `sudo systemctl restart rai-stats-queue.service` (do not
restart the scheduler timer, it's stateless per tick).

## Option B — Docker / docker-compose

Same four concerns become separate containers/services sharing one image and
one `.env`:

- `app` — php-fpm, serves the app (behind nginx, either in the same
  container or a sidecar).
- `queue` — same image, entrypoint overridden to
  `php artisan queue:work --tries=1 --timeout=240`.
- `scheduler` — same image, entrypoint overridden to run `php artisan
  schedule:work` (the long-running variant is simpler than cron-in-a-container
  for a single scheduler process).
- `db` — Postgres, with a named volume for data.

```yaml
services:
  app:
    build: .
    env_file: .env
    depends_on: [db]
    ports: ["8080:80"]

  queue:
    build: .
    env_file: .env
    depends_on: [db]
    command: php artisan queue:work --tries=1 --timeout=240
    restart: unless-stopped

  scheduler:
    build: .
    env_file: .env
    depends_on: [db]
    command: php artisan schedule:work
    restart: unless-stopped

  db:
    image: postgres:16
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - db-data:/var/lib/postgresql/data

volumes:
  db-data:
```

`restart: unless-stopped` on `queue` matters more than usual here: a crashed
worker mid-`RaiffeisenLoginJob` should come back and pick up the next job
rather than silently stop processing imports.

Build step (`npm run build`) should happen in a build stage of the
Dockerfile, with only the compiled `public/build` assets copied into the
final image — no Node needed at runtime.

## Local dev (reference)

DDEV already does all of this for you via `.ddev/config.yaml`'s
`web_extra_daemons` entry (`queue-worker`, running `queue:work --tries=1
--timeout=240`). There's no scheduler daemon configured locally since
nothing is scheduled yet — add one the same way if that changes.
