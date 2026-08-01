#!/usr/bin/env bash
#
# chatter — one-shot provisioning for Ubuntu 24.04 / 26.04
#
# Run as a sudo-capable user (the script calls sudo itself):
#   ./setup.sh --domain chat.example.com --email you@example.com
#
# What it does:
#   1. Installs base packages via apt: nginx, php(+fpm+extensions), composer,
#      nodejs 22 (nodesource), supervisor, certbot, libreoffice/poppler/
#      imagemagick (preview generation) and ONLYOFFICE Document Server.
#   2. Clones git@github.com:askdkc/chatter.git into /var/www/chatter,
#      runs composer install / npm build / migrate.
#   3. Generates nginx site configs (app + OnlyOffice reverse proxy),
#      supervisor configs (queue worker / Reverb / scheduler).
#   4. Issues a Let's Encrypt certificate for the app domain (+ office
#      subdomain) and enables auto-renewal via certbot.timer.
#   5. Enables every service for boot-time start.
#
set -euo pipefail

# ---------------------------------------------------------------- helpers --
log()  { printf '\033[1;34m[chatter]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[chatter:warn]\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[chatter:error]\033[0m %s\n' "$*" >&2; exit 1; }

APP_DIR="${APP_DIR:-/var/www/chatter}"
REPO_URL="${REPO_URL:-git@github.com:askdkc/chatter.git}"
DOMAIN=""
EMAIL=""
OFFICE_DOMAIN=""
NO_SSL=0

usage() {
    cat <<'EOF'
Usage: sudo ./setup.sh [options]

Options:
  --domain <domain>          App domain, e.g. chat.example.com (required unless --no-ssl)
  --email <email>            Let's Encrypt registration / renewal mail
  --office-domain <domain>   OnlyOffice domain (default: office.<domain>)
  --app-dir <path>           App install path (default: /var/www/chatter)
  --repo <url>               Git repo to deploy (default: git@github.com:askdkc/chatter.git)
  --no-ssl                   Skip Let's Encrypt (HTTP only, for testing)
  -h, --help                 Show this help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain)        DOMAIN="$2"; shift 2 ;;
        --email)         EMAIL="$2"; shift 2 ;;
        --office-domain) OFFICE_DOMAIN="$2"; shift 2 ;;
        --app-dir)       APP_DIR="$2"; shift 2 ;;
        --repo)          REPO_URL="$2"; shift 2 ;;
        --no-ssl)        NO_SSL=1; shift ;;
        -h|--help)       usage; exit 0 ;;
        *) die "Unknown option: $1 (see --help)" ;;
    esac
done

[[ $NO_SSL -eq 1 ]] || [[ -n "$DOMAIN" ]] || die "--domain is required (or use --no-ssl)"
[[ $NO_SSL -eq 1 ]] && [[ -n "$DOMAIN" ]] && warn "--no-ssl given; --domain ignored for certbot"
[[ -n "$EMAIL" ]] || warn "no --email given; certbot will run without renewal mail"

# ----------------------------------------------------------- preflight -----
command -v sudo >/dev/null || die "sudo is required"
sudo -n true 2>/dev/null || die "passwordless sudo required (run: sudo visudo -f /etc/sudoers.d/chatter)"
[[ "$(id -u)" -ne 0 ]] || die "run as a regular sudo user, not root (script uses sudo internally)"

. /etc/os-release
case "$VERSION_ID" in
    24.04|26.04) ;;
    *) die "Unsupported Ubuntu version: $VERSION_ID (support: 24.04, 26.04)" ;;
esac
log "Ubuntu $VERSION_ID ($VERSION_CODENAME) on $(dpkg --print-architecture)"

# --------------------------------------------------- base packages --------
log "Installing base packages (nginx, php, tools, preview stack)..."
sudo apt-get update -y
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
    nginx curl git unzip ca-certificates gnupg lsb-release \
    supervisor certbot python3-certbot-nginx \
    libreoffice poppler-utils imagemagick \
    ttf-mscorefonts-installer || true   # mscorefonts EULA prompt is non-fatal

# --------------------------------------------- PHP (detect distro default) --
log "Installing PHP (distro default) + extensions..."
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
    php-cli php-fpm php-common php-opcache php-curl php-mbstring \
    php-xml php-zip php-bcmath php-intl php-sqlite3 php-gd

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
log "PHP ${PHP_VER} active, FPM socket: ${PHP_FPM_SOCK}"
php -r 'echo "PDO drivers: ", implode(",", PDO::getAvailableDrivers()), PHP_EOL;'

# ------------------------------------------------------------- composer ----
if ! command -v composer >/dev/null; then
    log "Installing Composer..."
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$EXPECTED_CHECKSUM" == "$ACTUAL_CHECKSUM" ]] || die "Composer installer checksum mismatch"
    sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi
composer --version

# ---------------------------------------------------------- nodejs 22 ------
if ! command -v node >/dev/null || [[ "$(node -v | tr -d 'v' | cut -d. -f1)" -lt 22 ]]; then
    log "Installing Node.js 22 (nodesource)..."
    curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y nodejs
fi
node --version && npm --version

# ---------------------------------------------------- ONLYOFFICE Docs ------
if ! dpkg -s onlyoffice-documentserver >/dev/null 2>&1; then
    log "Installing ONLYOFFICE Document Server (port 8080, JWT)..."
    [[ -n "${ONLYOFFICE_JWT_SECRET:-}" ]] || ONLYOFFICE_JWT_SECRET="$(openssl rand -hex 24)"
    curl -fsSL https://download.onlyoffice.com/GPG-KEY-ONLYOFFICE | \
        sudo gpg --batch --yes --dearmor -o /usr/share/keyrings/onlyoffice.gpg
    echo "deb [signed-by=/usr/share/keyrings/onlyoffice.gpg] https://download.onlyoffice.com/repo/debian squeeze main" | \
        sudo tee /etc/apt/sources.list.d/onlyoffice.list >/dev/null
    echo "onlyoffice-documentserver onlyoffice/ds-port select 8080" | sudo debconf-set-selections
    echo "onlyoffice-documentserver onlyoffice/jwt-enabled boolean true" | sudo debconf-set-selections
    echo "onlyoffice-documentserver onlyoffice/jwt-secret password ${ONLYOFFICE_JWT_SECRET}" | sudo debconf-set-selections
    sudo apt-get update -y
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y onlyoffice-documentserver
else
    log "ONLYOFFICE Document Server already installed"
fi

# --------------------------------------------------------- app deploy ------
log "Cloning chatter into ${APP_DIR}..."
sudo mkdir -p "$APP_DIR"
sudo chown -R "$USER":"$USER" "$(dirname "$APP_DIR")/$(basename "$APP_DIR")"
if [[ -d "$APP_DIR/.git" ]]; then
    warn "repo already present; pulling instead of cloning"
    git -C "$APP_DIR" pull --ff-only 2>/dev/null || true
else
    GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=accept-new" \
        git clone "$REPO_URL" "$APP_DIR" || {
            warn "SSH clone failed; retrying over HTTPS"
            HTTPS_REPO="${REPO_URL/git@github.com:/https:\/\/github.com\/}"
            git clone "$HTTPS_REPO" "$APP_DIR"
        }
fi

cd "$APP_DIR"

log "Installing PHP dependencies (no-dev)..."
composer install --no-dev --no-interaction --prefer-dist

log "Preparing .env..."
if [[ ! -f .env ]]; then
    cp .env.example .env
fi
# Fill in production values without clobbering existing settings.
sed -i "s|^APP_NAME=.*|APP_NAME=chatter|" .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
if [[ -n "$DOMAIN" ]]; then
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
fi
sed -i "s|^BROADCAST_CONNECTION=.*|BROADCAST_CONNECTION=reverb|" .env
sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" .env
sed -i "s|^CACHE_STORE=.*|CACHE_STORE=database|" .env

# Fill Reverb / OnlyOffice values (empty placeholders from .env.example).
grep -q '^REVERB_APP_KEY=.\+' .env || sed -i "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=$(openssl rand -hex 16)|" .env
grep -q '^REVERB_APP_SECRET=.\+' .env || sed -i "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$(openssl rand -hex 24)|" .env
grep -q '^REVERB_APP_ID=.\+' .env || sed -i "s|^REVERB_APP_ID=.*|REVERB_APP_ID=chatter|" .env
grep -q '^REVERB_HOST=.\+' .env || sed -i "s|^REVERB_HOST=.*|REVERB_HOST=${DOMAIN:-localhost}|" .env
grep -q '^ONLYOFFICE_JWT_SECRET=.\+' .env || sed -i "s|^ONLYOFFICE_JWT_SECRET=.*|ONLYOFFICE_JWT_SECRET=${ONLYOFFICE_JWT_SECRET:-$(openssl rand -hex 24)}|" .env
sed -i "s|^ONLYOFFICE_ENABLED=.*|ONLYOFFICE_ENABLED=true|" .env
sed -i "s|^ONLYOFFICE_DOCUMENT_SERVER_URL=.*|ONLYOFFICE_DOCUMENT_SERVER_URL=https://${OFFICE_DOMAIN:-office.${DOMAIN:-localhost}}|" .env
sed -i "s|^ONLYOFFICE_PUBLIC_URL=.*|ONLYOFFICE_PUBLIC_URL=https://${OFFICE_DOMAIN:-office.${DOMAIN:-localhost}}|" .env
sed -i "s|^ONLYOFFICE_ALLOW_DOWNLOAD=.*|ONLYOFFICE_ALLOW_DOWNLOAD=true|" .env
sed -i "s|^ONLYOFFICE_ALLOW_PRINT=.*|ONLYOFFICE_ALLOW_PRINT=true|" .env
grep -q '^REVERB_PORT=.*' .env || echo "REVERB_PORT=443" >> .env
grep -q '^REVERB_SCHEME=.*' .env || echo "REVERB_SCHEME=https" >> .env

# Vite embeds VITE_* vars at build time; keep them in sync with REVERB_*.
for v in APP_KEY HOST PORT SCHEME; do
    grep -q "^VITE_REVERB_${v}=" .env || echo "VITE_REVERB_${v}=" >> .env
    val="$(grep -oP "^REVERB_${v}=\K.*" .env | head -1)"
    sed -i "s|^VITE_REVERB_${v}=.*|VITE_REVERB_${v}=\"${val}\"|" .env
done

grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

log "Building frontend..."
npm ci --no-audit --no-fund
npm run build

log "Running migrations..."
touch database/database.sqlite
php artisan migrate --force

log "Setting storage permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rw storage bootstrap/cache
sudo chown -R "$USER":www-data "$APP_DIR"

# --------------------------------------------------------- nginx conf ------
log "Generating nginx site configs..."
OFFICE_HOST="${OFFICE_DOMAIN:-office.${DOMAIN}}"

sudo tee "/etc/nginx/sites-available/chatter" >/dev/null <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    root ${APP_DIR}/public;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Laravel public assets (Vite build output)
    location ~* \.(?:css|js|jpg|jpeg|png|gif|svg|ico|webp|woff2?|ttf|eot|mp4|webm)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        internal;
    }

    # Reverb WebSocket endpoint (wss)
    location /apps/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 60s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

# OnlyOffice reverse proxy (Document Server listens on 127.0.0.1:8080)
sudo tee "/etc/nginx/sites-available/onlyoffice" >/dev/null <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${OFFICE_HOST};

    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 600s;
    }
}
NGINX

sudo ln -sfn /etc/nginx/sites-available/chatter /etc/nginx/sites-enabled/chatter
sudo ln -sfn /etc/nginx/sites-available/onlyoffice /etc/nginx/sites-enabled/onlyoffice
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t

# ---------------------------------------------------- supervisor conf ------
log "Generating supervisor configs..."
sudo tee /etc/supervisor/conf.d/chatter-queue.conf >/dev/null <<SUP
[program:chatter-queue]
directory=${APP_DIR}
command=php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
user=${USER}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/chatter-queue.log
stderr_logfile=/var/log/chatter-queue-error.log
SUP

sudo tee /etc/supervisor/conf.d/chatter-reverb.conf >/dev/null <<SUP
[program:chatter-reverb]
directory=${APP_DIR}
command=php artisan reverb:start --host=127.0.0.1 --port=8080
user=${USER}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/chatter-reverb.log
stderr_logfile=/var/log/chatter-reverb-error.log
SUP

sudo tee /etc/supervisor/conf.d/chatter-schedule.conf >/dev/null <<SUP
[program:chatter-schedule]
directory=${APP_DIR}
command=php artisan schedule:work
user=${USER}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/chatter-schedule.log
stderr_logfile=/var/log/chatter-schedule-error.log
SUP

# ------------------------------------------------- Let's Encrypt SSL -------
if [[ $NO_SSL -eq 1 ]]; then
    warn "--no-ssl: skipping certbot (HTTP only)"
else
    log "Issuing Let's Encrypt certificate for ${DOMAIN} + ${OFFICE_HOST}..."
    CERTS=(--nginx -d "$DOMAIN" -d "$OFFICE_HOST" --redirect --non-interactive --agree-tos)
    [[ -z "$EMAIL" ]] || CERTS+=(--email "$EMAIL")
    if sudo certbot "${CERTS[@]}" --no-self-upgrade; then
        sudo systemctl enable certbot.timer
        sudo systemctl start certbot.timer
        log "SSL issued; auto-renewal enabled (certbot.timer)"
    else
        warn "certbot failed — check DNS A records for ${DOMAIN} and ${OFFICE_HOST} point at this host"
    fi
fi

# ---------------------------------------------------- enable services ------
log "Enabling services for boot..."
sudo systemctl enable nginx "php${PHP_VER}-fpm" supervisor postgresql onlyoffice-documentserver

log "Reloading services..."
sudo systemctl reload nginx 2>/dev/null || sudo systemctl restart nginx
sudo systemctl restart "php${PHP_VER}-fpm" || true
sudo systemctl restart supervisor || true
sudo systemctl restart onlyoffice-documentserver || true

# ------------------------------------------------------------ verify -------
log "Verification..."
sudo supervisorctl reread >/dev/null
sudo supervisorctl update >/dev/null
sudo supervisorctl status || true
sleep 3
if [[ -n "$DOMAIN" ]]; then
    curl -fsS -o /dev/null "http://127.0.0.1/up" && log "App responds on /up" || warn "/up not reachable yet (expected if certbot redirects to https)"
    curl -fsS -o /dev/null "https://127.0.0.1/up" -k && log "App responds on /up (https)" || warn "https check failed"
fi
php artisan about --only=environment 2>/dev/null | head -12 || true

log "Done."
log "App:        https://${DOMAIN:-<your-domain>} (first user registers via /register)"
log "OnlyOffice: https://${OFFICE_HOST}"
log "Logs:       sudo supervisorctl tail -f chatter-queue   | sudo tail -f /var/log/chatter*.log"
log "Manual:     cd ${APP_DIR} && php artisan ..."
