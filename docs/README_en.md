# 茶多楼 chatterrow

## Business management features packed into chat for unified operations
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/f7032613-194f-46b6-ac77-cfb1a4f2f1a3" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/619f867d-9598-4628-8e94-89eac10558d1" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/981ccc86-ae70-4122-bfb3-93a6d01cce29" />


Built with Laravel 13, Inertia 3, and Svelte 5, this is project-based groupware with a Discord-style UI.

Create channels for each project and centrally manage chats, tasks, files, and Gantt charts. Laravel Reverb is used for real-time delivery, while ONLYOFFICE Document Server is used for read-only previews of Office files.

## Benefits of on-premises groupware (茶多楼 provides the App Server service)
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## Main features

- **Project management**: Set the project name, description, start date, end date, and members
- **Channels**: Organize conversations, tasks, and files within a project by channel
- **Real-time chat**: Synchronize messages, threads, and reply counts with Laravel Reverb
- **Secure Markdown**: HTML escaping, HTTP(S) URL restrictions, and Shiki code highlighting
- **Attachments**: File/folder D&D, uploads in batches of 10, and thumbnails for images, PDFs, and Office files
- **File previews**: A centered viewer for images and PDFs, Office previews with ONLYOFFICE, and exit with Esc
- **Office/PDF Markdown conversion and storage**: Convert files to Markdown in the background to make them easier to use for AI training
- **Task management**: Start date/time, end date/time, priority, notes, and completion status
- **Gantt charts**: Display periods by project or channel
- **Due-date reminders**: Automatic notifications through the scheduler and queue workers
- **Themes**: Dark/light mode support
- **Keyboard controls**: Send messages and create tasks with `Cmd+Enter` or `Ctrl+Enter`. Enter used to confirm IME input does not send

## Technology stack

| Layer      | Technology                                                    |
|------------|---------------------------------------------------------------|
| Backend    | Laravel 13 / PHP 8.5+                                         |
| Frontend   | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8                |
| Database   | SQLite or PostgreSQL                                          |
| Realtime   | Laravel Reverb (WebSocket)                                    |
| Preview    | Shiki / ONLYOFFICE / poppler / ImageMagick                    |
| Conversion | Microsoft MarkItDown 0.1.7 (PDF / DOCX / XLSX / PPTX)         |
| Queue      | Redis / Laravel queue worker                                  |
| Office     | ONLYOFFICE Document Server Community Edition (JWT, read-only) |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot          |

## Production requirements

- Ubuntu 24.04 LTS or Ubuntu 26.04 LTS (amd64)
- PHP 8.5 CLI/FPM and the Redis extension
- Python 3.10 or later, MarkItDown 0.1.7, and Redis Server
- A regular user who can use sudo, or the root user
- 2 CPUs, 2 GB RAM, and at least 30 GB of free disk space (the official ONLYOFFICE recommendation is at least 40 GB)
- At least 4 GB of swap is recommended
- Make TCP 80/443 reachable from the Internet
- A DNS name for the application

Example:

```text
chat.example.com  A/AAAA -> server
```

ONLYOFFICE is published at `/onlyoffice/` on the same domain as the application. Do not expose ONLYOFFICE, Reverb, or the internal application-fetch ports 8080, 8081, and 8090 to the outside. In cloud or host-side firewalls, allow only the SSH port and 80/443.

## Automatic setup on Ubuntu

On a new Ubuntu Server, obtain this repository and run `setup.sh`. The domain, database, and Let's Encrypt email address are confirmed interactively. It can be run by a regular user or the root user.

```bash
apt install -y git

git clone https://github.com/askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

For local development/validation environments, private networks, and other cases where a public domain reachable from the Internet and HTTPS are unnecessary, run with `--no-ssl`. Let's Encrypt will not be used; the setup will use HTTP only. When using a local domain such as `chatterrow.test`, configure DNS or `/etc/hosts` in advance so that the name resolves to this server.

```bash
./setup.sh --domain chatterrow.test --database sqlite --no-ssl
```

When running `setup.sh`, you will be asked for the sudo password. If you are a user who can use sudo without a sudo password, run it with the `--sudo-nopasswd` option as in the example below.


```bash
./setup.sh --sudo-nopasswd
```

If it is run without options and passwordless sudo is detected, the setup does not start; it exits after displaying how to use `--sudo-nopasswd`.

Example input when running `setup.sh`:

```text
Application domain (e.g. chat.example.com): chat.example.com
Application database:
  1) sqlite
  2) postgresql
Select database (default: 1): 2
Let's Encrypt email (optional): admin@example.com
(omitted)
PostgreSQL password (leave blank to generate):
```

The public URL for ONLYOFFICE is `https://<アプリドメイン>/onlyoffice`. In the example above, it is `https://chat.example.com/onlyoffice`.

The setup performs the following automatically:

1. Configure Ubuntu's official `nginx-extras`, PHP 8.5, PostgreSQL, Redis, RabbitMQ, and Node.js 24
2. Install PHP extensions, Poppler, ImageMagick, Ghostscript, and Japanese fonts with apt, and set ImageMagick's absolute path in `.env`
3. Set up MarkItDown 0.1.7 in the Python virtual environment `.markitdown/venv`, and verify it with `pip check` and the CLI version
4. Tune PostgreSQL according to the number of CPUs and the installed RAM
5. When PostgreSQL is selected, configure a `SUPERUSER LOGIN` role with the same name as the execution user (`DEPLOY_USER`)
6. Install ONLYOFFICE Document Server with JWT enabled on internal port 8080 (changeable with `ONLYOFFICE_PORT`)
7. Apply dependencies, the frontend, and migrations to the cloned repository
8. Configure the application, ONLYOFFICE, Reverb, and the internal ONLYOFFICE download route in nginx
9. Keep 10 Redis queue processes, Reverb, and the scheduler running as `www-data` under Supervisor
10. Issue a certificate with Certbot and enable `certbot.timer` and the nginx reload hook
11. Apply Ubuntu security updates, including nginx, daily with `unattended-upgrades`
12. Run health checks for PHP 8.5, Redis, PostgreSQL, ONLYOFFICE, Supervisor, and the application

nginx uses only Ubuntu APT packages.

## macOS local OnlyOffice

On macOS, do not install the Linux OnlyOffice package; start DocumentServer with Apple's `container`. Apple silicon and macOS 26 or later are required.

1. Install [Apple Container](https://github.com/apple/container).
2. Install ImageMagick with `brew install imagemagick`.
3. Prepare the Laravel application's `.env`. If it does not exist, `setup.sh` copies `.env.example`.
4. Set `APP_URL` to the actual local URL.
5. Run setup from the repository root. On macOS, `--domain` and `--database` are unnecessary.

```bash
cd /path/to/chatterrow
./setup.sh
```

The entire existing `.env` is not overwritten. The update targets the following ONLYOFFICE settings and the detected absolute path to ImageMagick.

```dotenv
ONLYOFFICE_ENABLED
ONLYOFFICE_DOCUMENT_SERVER_URL
ONLYOFFICE_PUBLIC_URL
APP_ONLYOFFICE_INTERNAL_URL
ONLYOFFICE_JWT_SECRET
ONLYOFFICE_ALLOW_DOWNLOAD
ONLYOFFICE_ALLOW_PRINT
IMAGEMAGICK_PATH
```

### macOS development: automatic detection of Valet, Herd, and artisan serve

`setup.sh` checks whether the `valet` and `herd` commands exist, as well as the responses from `APP_URL/up` and `127.0.0.1:<ポート>/up`, to select the connection method. If both the Valet/Herd side and the artisan serve side respond, the Valet/Herd side of `APP_URL` takes priority.

| Development server   | Example `.env` `APP_URL`    | Automatically configured `APP_ONLYOFFICE_INTERNAL_URL`   |
|----------------------|-----------------------------|----------------------------------------------------------|
| Laravel Valet         | `http://chatterrow.test`     | `http://chatterrow.test`                                 |
| Laravel Herd          | `http://chatterrow.test`     | `http://chatterrow.test`                                 |
| `php artisan serve`   | `http://localhost:8000`      | `http://chatter-host.container.internal:8000`            |

When using artisan serve, start it before setup or before opening a preview.

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

To override automatic detection:

```bash
MACOS_APP_SERVER=artisan ./setup.sh
MACOS_APP_SERVER=artisan MACOS_ARTISAN_PORT=9000 ./setup.sh
MACOS_APP_SERVER=valet ./setup.sh
MACOS_APP_SERVER=herd ./setup.sh
```

The setup does the following:

- Start Apple Container with `container system start`
- Pull `onlyoffice/documentserver:latest` every time and start it on arm64
- Publish OnlyOffice at `127.0.0.1:8086`
- Allocate 4 CPUs, 4 GB of memory, and 2 GB of shared memory
- Pin the container's DNS server to `1.1.1.1`
- Enable JWT and set the shared secret key in `.env`
- Recreate the OnlyOffice container every time `setup.sh` is run on macOS while retaining named volumes
- Connect `chatter-host.container.internal` to macOS loopback through `203.0.113.150`
- In Valet/Herd, assign the application's hostname to `203.0.113.150` only inside the container
- Download and verify Source Han Sans JP / Noto Serif CJK JP, then regenerate the OnlyOffice font list
- Update `ONLYOFFICE_DOCUMENT_SERVER_URL`, `ONLYOFFICE_PUBLIC_URL`, and `APP_ONLYOFFICE_INTERNAL_URL`
- Health-check DocumentServer and Laravel's `/up`

To override the DNS server, specify the environment variable. Specify the value as an IPv4 address.

Example of changing Cloudflare's 1.1.1.1 to Google's 8.8.8.8:
```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

The following named volumes are used for persistent data.

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

Inspect and stop the container:

```bash
container list
container logs chatterrow-onlyoffice-documentserver
container stop chatterrow-onlyoffice-documentserver
```

Health check:

```bash
curl -fsS http://127.0.0.1:8086/healthcheck
container exec chatterrow-onlyoffice-documentserver \
    curl -fsS --max-time 5 "$(sed -n 's/^APP_ONLYOFFICE_INTERNAL_URL=//p' .env)/up"
```

### Full container and volume initialization test

The following operations delete OnlyOffice's internal PostgreSQL database, settings, cache, and logs. Do not run them if you have data that you need.

```bash
container stop chatterrow-onlyoffice-documentserver
container delete chatterrow-onlyoffice-documentserver
container volume delete \
    chatterrow-onlyoffice-data \
    chatterrow-onlyoffice-logs \
    chatterrow-onlyoffice-cache \
    chatterrow-onlyoffice-postgresql
./setup.sh
```

[Apple Container named volumes](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management) are implicitly created when they do not exist by `container run --volume <名前>:<パス>`, so the procedure above also verifies initial setup including volume creation.

### Japanese fonts (macOS)

Installing fonts on the macOS host does not make them available to the isolated OnlyOffice container. `setup.sh` downloads the following fixed-weight fonts from official repositories at pinned versions, verifies their SHA-256 hashes, and then registers them with OnlyOffice.

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold (2.005R, JP subset OTF)
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold (Serif2.003, Japanese OTF)

The fonts are stored in `/var/www/onlyoffice/Data/custom-fonts` inside the `chatterrow-onlyoffice-data` volume. When setup is run again, it checks the SHA-256 hashes of the files inside the container and does not download them again if they match.

After registering the fixed-weight versions, `setup.sh` deletes the existing `AllFonts.js` and `font_selection.bin` and then runs `allfontsgen`. If the existing files are left in place, `allfontsgen` may reuse the catalog and fail to register the new fonts.

OnlyOffice 9.4's converter may replace Microsoft Office Japanese theme fonts with `NanumGothic` or `Droid Sans Fallback`. fontconfig aliases alone do not affect this conversion path. Therefore, [scripts/patch-onlyoffice-font-catalog.php](../scripts/patch-onlyoffice-font-catalog.php) applies the following corrections to the server-side `font_selection.bin` and the two browser-side `AllFonts.js` files.

- Register aliases for the 游ゴシック, Yu Gothic, Meiryo, and MS Gothic families to Source Han Sans JP
- Register aliases for the 游明朝, Yu Mincho, and MS Mincho families to Noto Serif CJK JP
- Change the actual font reference selected by the converter for `NanumGothic` to Source Han Sans JP
- Change the actual font reference selected by the converter for `Droid Sans Fallback` to Noto Serif CJK JP

This catalog is shared by DOCX, XLSX, and PPTX conversion and browser display. After applying the corrections, a JS cache is generated, docservice and converter are restarted, and the DocumentServer cache is cleared. If the catalog format or required font names change in a future `latest`, setup exits with an error instead of using an incorrect catalog.

The standard `documentserver-generate-allfonts.sh` is not called because it also regenerates unnecessary presentation themes, and that process may not finish in the Apple Container environment.

When the font catalog is updated, the OnlyOffice document-cache generation also changes, so already-open DOCX files are reconverted instead of reusing an old `Editor.bin`.

If an existing container has stopped responding at `Generating presentation themes`, simply run `./setup.sh` again. On macOS, each run pulls `onlyoffice/documentserver:latest` and forcibly recreates the existing OnlyOffice container. The four named volumes above are not deleted, so OnlyOffice's persistent data is retained.

Microsoft's 游明朝 / 游ゴシック fonts are not bundled or redistributed. The following substitute settings are used inside the OnlyOffice container.

| Office-specified font (DOCX / XLSX / PPTX)       | Substitute font       |
|--------------------------------------------------|-----------------------|
| 游明朝 / Yu Mincho / MS Mincho                   | Noto Serif CJK JP     |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic      | Source Han Sans JP    |

Missing characters and substitution with unsuitable Latin fonts are eliminated, but because character widths differ from the 游 fonts, there is no guarantee that line breaks or page counts will match perfectly. If exact matching is required, separately place the actual 游 fonts, after confirming their license terms, in OnlyOffice's custom-font area.

Confirm the registration:

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

The expected values are `1.1.1.1`, `NotoSerifCJKjp-Regular.otf`, and `SourceHanSansJP-Light.otf`, respectively.

On macOS, OnlyOffice editing permission is not granted; it remains a ReadOnly preview. The DocumentServer conversion API can also be used independently of this ReadOnly setting.

## Non-interactive setup

In Ubuntu automation environments, `--domain` and `--database` are required. They are unnecessary for the macOS local OnlyOffice setup.

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### Options

| Option                       | Default                            | Description                                           |
|------------------------------|------------------------------------|-------------------------------------------------------|
| `--domain <domain>`          | Interactive input                  | Public domain of the application                     |
| `--email <email>`            | Empty                              | Let's Encrypt registration and expiration notices     |
| `--database <driver>`        | `sqlite` during interactive input | `sqlite` or `postgresql`                              |
| `--db-name <name>`           | `chatterrow`                       | PostgreSQL database name for the application          |
| `--db-user <name>`           | `chatterrow`                       | PostgreSQL role for the application                   |
| `--db-password <password>`   | Automatically generated            | PostgreSQL password for the application               |
| `--app-dir <path>`           | Repository containing `setup.sh`  | Deployment path under `/home` or `/var/www`           |
| `--repo <url>`               | GitHub SSH URL                     | Git repository to deploy                              |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest` | DocumentServer image pulled and used each time on macOS |
| `--sudo-nopasswd`            | off                                | For passwordless sudo users; omits `sudo -v`          |
| `--no-ssl`                   | off                                | Omits Certbot and configures HTTP                    |

The corresponding uppercase environment variables can also be used. Examples: `DOMAIN`, `DATABASE`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DEPLOY_USER`, `SUDO_NOPASSWD`, `ONLYOFFICE_PORT`, `ONLYOFFICE_JWT_SECRET`.

If the PostgreSQL password is omitted, a 64-character random value is generated and saved in the following locations.

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

When PostgreSQL is selected, a PostgreSQL role with the same name as the execution user (`DEPLOY_USER`, or `id -un` when run by a regular user if unspecified, or `root` when run as root) is created or updated as `LOGIN SUPERUSER`. The `DB_USER` used for the application connection remains a separate unprivileged role.

## PostgreSQL automatic tuning

PostgreSQL is also required by ONLYOFFICE, so it is installed and tuned even when SQLite is selected for the application. If multiple PostgreSQL clusters exist on Ubuntu, setup stops to avoid modifying the wrong cluster.

Configuration file:

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

Main calculation criteria:

| Setting                | Criteria                                             |
|------------------------|------------------------------------------------------|
| `shared_buffers`       | 20% of RAM, from 128 MB to 8 GB                      |
| `effective_cache_size` | 60% of RAM, from 256 MB to 64 GB                    |
| `maintenance_work_mem` | 5% of RAM, from 64 MB to 1 GB                       |
| `work_mem`             | Conservatively calculated from RAM, `shared_buffers`, and the maximum number of connections |
| `max_connections`      | Calculated from CPU and RAM, between 50 and 300      |
| parallel workers       | Calculated from the number of CPUs and capped       |

Because PHP, ONLYOFFICE, Redis, and RabbitMQ also share this server, allocations are more conservative than on a PostgreSQL-only server. Re-running recalculates them from the current number of CPUs and RAM.

## Port configuration

Ubuntu production environment:

| Port     | Purpose                              | Exposure            |
|----------|--------------------------------------|---------------------|
| 80 / 443 | nginx, Certbot, public web           | Internet            |
| 8080     | ONLYOFFICE Document Server           | For localhost       |
| 8081     | Laravel Reverb                      | For localhost       |
| 8090     | Retrieve signed files from ONLYOFFICE | 127.0.0.1 only    |
| 5432     | PostgreSQL                          | Local connections recommended |

macOS local environment:

| Port | Purpose                                        | Exposure          |
|------|------------------------------------------------|-------------------|
| 8086 | ONLYOFFICE Document Server on Apple Container | `127.0.0.1` only |
| 8000 | Default port for `php artisan serve`          | `127.0.0.1` only |

When using Valet/Herd, the Laravel application runs on the hostname in `APP_URL` and the usual HTTP/HTTPS port. The artisan serve port is determined from `APP_URL` or `MACOS_ARTISAN_PORT`.

## SSL and automatic renewal

Certbot uses `/var/www/letsencrypt` as a fixed ACME webroot and configures the application domain's certificate in nginx without passing the challenge to Laravel. `/onlyoffice/` is served with the same certificate. `certbot.timer` periodically checks when renewal is due and reloads nginx after a successful renewal. A dry run is also performed during setup.

`unattended-upgrades` checks Ubuntu security origins daily and updates `nginx`, `nginx-extras`, and the corresponding `libnginx-mod-*` packages with their dependencies. Security updates other than nginx are also maintained. The normal `-updates` pocket is not applied automatically.

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
sudo systemctl status apt-daily-upgrade.timer
sudo unattended-upgrade --dry-run --debug
```

## Operations

### Process checks

```bash
php8.5 --version
php8.5 -m | grep -E 'redis|pdo_sqlite|pdo_pgsql'
redis-cli ping
sudo supervisorctl status 'chatterrow-queue:*'
sudo supervisorctl restart 'chatterrow-queue:*'
sudo supervisorctl restart chatterrow-reverb chatterrow-schedule
sudo tail -f /var/log/chatterrow-queue_*.log /var/log/chatterrow-queue-error_*.log
```

Queue workers run `/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600` across 10 processes. Confirm that all 10 are `RUNNING`.

### Reprocessing Markdown conversions

Requeue failed files and `pending`/`processing` files that have not been updated for a certain period.

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

`files:markdown` does not target legacy Office formats (DOC, XLS, PPT, ODF) for Markdown conversion. Their ONLYOFFICE preview functionality remains available.

### Updating the application

You can run `setup.sh` again with the same settings. The existing PostgreSQL password and TLS-enabled nginx configuration are retained, and Git is updated only when a fast-forward is possible.

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### Backups

SQLite:

```bash
sudo install -d /backup
sudo -u www-data sqlite3 /home/ubuntu/chatterrow/database/database.sqlite \
    ".backup /backup/chatterrow-$(date +%F).sqlite"
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

PostgreSQL:

```bash
sudo install -d /backup
sudo -u postgres pg_dump --format=custom chatterrow > /backup/chatterrow-$(date +%F).dump
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

## Main environment variables

| Variable                         | Description                                            |
|----------------------------------|--------------------------------------------------------|
| `APP_URL`                        | Public URL of the application                          |
| `DB_CONNECTION`                  | `sqlite` or `pgsql`                                    |
| `QUEUE_CONNECTION`               | Redis queue (`redis`)                                  |
| `MARKITDOWN_PATH`                | Path to the MarkItDown CLI; inside `.markitdown/venv` when unspecified |
| `MARKITDOWN_TIMEOUT`             | Conversion timeout in seconds per file                 |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | Minimum Python version required by the MarkItDown environment (3.10) |
| `IMAGEMAGICK_PATH`               | Absolute path to `magick` or `convert`                 |
| `REVERB_APP_ID/KEY/SECRET`       | Reverb credentials                                      |
| `REVERB_HOST/PORT/SCHEME`        | Public WebSocket to which the browser and Laravel connect |
| `REVERB_SERVER_HOST/PORT`        | Reverb's internal listen address; setup uses `127.0.0.1:8081` |
| `REVERB_ALLOWED_ORIGINS`         | Public domains allowed to connect to Reverb             |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | Internal ONLYOFFICE URL used by Laravel (127.0.0.1 on Ubuntu) |
| `ONLYOFFICE_PUBLIC_URL`           | Public ONLYOFFICE URL visible to the browser             |
| `APP_ONLYOFFICE_INTERNAL_URL`    | Internal application URL used by ONLYOFFICE to fetch files |
| `ONLYOFFICE_JWT_SECRET`          | JWT secret shared with ONLYOFFICE                       |

## Troubleshooting

| Symptom                              | Check                                                                                                          |
|--------------------------------------|----------------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                      | `sudo systemctl status php*-fpm`, `sudo nginx -t`                                                               |
| Real-time updates do not appear     | `sudo supervisorctl status chatterrow-reverb`, the `/app/` connection in the browser Network panel            |
| Attachment preview is not generated | `/var/log/chatterrow-queue-error_*.log`, ONLYOFFICE/Poppler/ImageMagick                                          |
| `exec: convert: not found`           | Set the absolute path from `command -v magick` or `command -v convert` in `IMAGEMAGICK_PATH`, then `php artisan optimize:clear` |
| Markdown conversion fails            | `storage/logs/laravel.log`, `/var/log/chatterrow-queue-error_*.log`, `php artisan files:markdown`              |
| Redis queue is not processed         | `redis-cli ping`, `redis` in `php8.5 -m`, `sudo supervisorctl status 'chatterrow-queue:*'`                    |
| Office preview does not open (Ubuntu) | `curl http://127.0.0.1:8080/healthcheck`, JWT secret, internal port 8090 URL, `php artisan files:previews`    |
| Office preview does not open (macOS)  | `curl http://127.0.0.1:8086/healthcheck`, JWT secret, `APP_ONLYOFFICE_INTERNAL_URL`, reachability of `/up` from inside the container |
| Cannot connect to PostgreSQL         | `.env`, `sudo -u postgres pg_isready`, `/etc/chatterrow/database-password`                                    |
| Certificate cannot be issued         | Application domain A/AAAA, reachability of port 80 from the Internet, `/var/log/letsencrypt/`                |

## Local development

```bash
composer install
composer markitdown:install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run dev
```

Start Laravel and Reverb in separate terminals.

```bash
php artisan serve
php artisan reverb:start --port=8081
php artisan queue:work redis
```

If Redis Server is not running, on macOS run `brew services start redis`, and on Ubuntu run `sudo systemctl enable --now redis-server`.

Verification:

```bash
php artisan test
php artisan files:markdown
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## License

MIT. If you install ONLYOFFICE Docs Community Edition, also review the AGPLv3 terms.
