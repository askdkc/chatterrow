#!/usr/bin/env bash
#
# chatterrow - one-shot provisioning for Ubuntu 24.04 / 26.04
#
# Run as a regular sudo-capable user. Missing values are prompted when the
# script is attached to a terminal:
#   ./setup.sh
#
# For unattended provisioning, pass all required values:
#   ./setup.sh --domain chat.example.com --email you@example.com --database postgresql
#
set -euo pipefail

# ---------------------------------------------------------------- helpers --
log()  { printf '\033[1;34m[chatterrow]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[chatterrow:warn]\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[chatterrow:error]\033[0m %s\n' "$*" >&2; exit 1; }

clamp() {
    local value="$1" minimum="$2" maximum="$3"

    (( value < minimum )) && value="$minimum"
    (( value > maximum )) && value="$maximum"
    printf '%s' "$value"
}

set_env() {
    local key="$1" value="$2" file="${3:-.env}" escaped
    escaped="$(printf '%s' "$value" | sed 's/[&|\\]/\\&/g')"

    if grep -Eq "^[[:space:]]*#?[[:space:]]*${key}=" "$file"; then
        sed -E -i.bak "s|^[[:space:]]*#?[[:space:]]*${key}=.*|${key}=${escaped}|" "$file"
        rm -f "${file}.bak"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

dotenv_quote() {
    local value="$1"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    value="${value//\$/\\$}"
    printf '"%s"' "$value"
}

valid_hostname() {
    [[ "$1" =~ ^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$ ]]
}

valid_local_hostname() {
    valid_hostname "$1" || [[ "$1" == "localhost" ]] || [[ "$1" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]]
}

valid_ipv4() {
    local address="$1" octet
    local -a octets

    IFS=. read -r -a octets <<< "$address"
    [[ "${#octets[@]}" -eq 4 ]] || return 1

    for octet in "${octets[@]}"; do
        [[ "$octet" =~ ^[0-9]{1,3}$ ]] || return 1
        (( 10#$octet <= 255 )) || return 1
    done
}

endpoint_returns_ok() {
    local response

    response="$(curl -fsS --max-time 3 "${1%/}/up" 2>/dev/null)" || return 1
    [[ "$response" == "ok" || "$response" == "OK" ]]
}

setup_onlyoffice_japanese_fonts() {
    local container_name="$1"
    local app_dir="$2"
    local font_dir="/var/www/onlyoffice/Data/custom-fonts"
    local catalog_patcher="$app_dir/scripts/patch-onlyoffice-font-catalog.php"
    local font_tmp_dir="" catalog_tmp_dir="" actual_sha256 installed_sha256 fonts_changed font_index
    local -a font_names=(
        "SourceHanSansJP-Light.otf"
        "SourceHanSansJP-Regular.otf"
        "SourceHanSansJP-Bold.otf"
        "NotoSerifCJKjp-Regular.otf"
        "NotoSerifCJKjp-Bold.otf"
    )
    local -a font_urls=(
        "https://raw.githubusercontent.com/adobe-fonts/source-han-sans/2.005R/SubsetOTF/JP/SourceHanSansJP-Light.otf"
        "https://raw.githubusercontent.com/adobe-fonts/source-han-sans/2.005R/SubsetOTF/JP/SourceHanSansJP-Regular.otf"
        "https://raw.githubusercontent.com/adobe-fonts/source-han-sans/2.005R/SubsetOTF/JP/SourceHanSansJP-Bold.otf"
        "https://raw.githubusercontent.com/notofonts/noto-cjk/Serif2.003/Serif/OTF/Japanese/NotoSerifCJKjp-Regular.otf"
        "https://raw.githubusercontent.com/notofonts/noto-cjk/Serif2.003/Serif/OTF/Japanese/NotoSerifCJKjp-Bold.otf"
    )
    local -a font_sha256=(
        "add5669f3ebb69ce21cff87a8a4c28388406fb07bd81b23d06c23d6461454988"
        "40d1b760d1135539f6b6e0ee2b9f415de6d97576f7676840b06306c7c190c074"
        "3a2722f94c97a53b172579a10ef8fc34b3fa8a6bb4f7947a2ec709ab647fb755"
        "d9854c7a8ef170b5a7932558856fd64eb8de0b007cd823fed6f9f514ad2803d3"
        "861a2b2c0e24b23745c262be8c3fdef63f12628f0492fb120ee51aa55c503af8"
    )

    fonts_changed=0

    command -v shasum >/dev/null || die "shasum is required to verify OnlyOffice fonts"
    command -v php >/dev/null || die "PHP is required to patch the OnlyOffice font catalog"
    [[ -f "$catalog_patcher" ]] || die "Could not find $catalog_patcher"
    container exec "$container_name" mkdir -p "$font_dir" \
        || die "Could not create the OnlyOffice custom font directory"

    for font_index in "${!font_names[@]}"; do
        installed_sha256="$(container exec "$container_name" sha256sum \
            "$font_dir/${font_names[$font_index]}" 2>/dev/null \
            | awk '{print $1}' || true)"
        [[ "$installed_sha256" != "${font_sha256[$font_index]}" ]] || continue

        if [[ -z "$font_tmp_dir" ]]; then
            log "Downloading verified Source Han Sans JP and Noto Serif CJK JP fonts for OnlyOffice..."
            font_tmp_dir="$(mktemp -d "${TMPDIR:-/tmp}/chatterrow-onlyoffice-fonts.XXXXXX")" \
                || die "Could not create a temporary font directory"
        fi

        if ! curl -fL --retry 3 --retry-delay 2 \
            --output "$font_tmp_dir/${font_names[$font_index]}" \
            "${font_urls[$font_index]}"; then
            rm -f "$font_tmp_dir"/*
            rmdir "$font_tmp_dir" 2>/dev/null || true
            die "Could not download ${font_names[$font_index]}"
        fi

        actual_sha256="$(shasum -a 256 "$font_tmp_dir/${font_names[$font_index]}" | awk '{print $1}')"
        if [[ "$actual_sha256" != "${font_sha256[$font_index]}" ]]; then
            rm -f "$font_tmp_dir"/*
            rmdir "$font_tmp_dir" 2>/dev/null || true
            die "${font_names[$font_index]} checksum mismatch"
        fi

        if ! container copy "$font_tmp_dir/${font_names[$font_index]}" \
            "${container_name}:/tmp/chatterrow-${font_names[$font_index]}" \
            || ! container exec "$container_name" sh -c \
                "cp '/tmp/chatterrow-${font_names[$font_index]}' '$font_dir/${font_names[$font_index]}' \
                    && rm -f '/tmp/chatterrow-${font_names[$font_index]}'"; then
            rm -f "$font_tmp_dir"/*
            rmdir "$font_tmp_dir" 2>/dev/null || true
            die "Could not copy ${font_names[$font_index]} into $container_name"
        fi

        installed_sha256="$(container exec "$container_name" sha256sum \
            "$font_dir/${font_names[$font_index]}" 2>/dev/null \
            | awk '{print $1}' || true)"
        [[ "$installed_sha256" == "${font_sha256[$font_index]}" ]] \
            || die "${font_names[$font_index]} verification failed inside $container_name"
        fonts_changed=1
    done

    if [[ -n "$font_tmp_dir" ]]; then
        rm -f "$font_tmp_dir"/*
        rmdir "$font_tmp_dir"
    fi

    if [[ $fonts_changed -eq 0 ]]; then
        log "Verified Japanese fonts are already installed in OnlyOffice"
    fi

    container exec "$container_name" sh -lc '
        set -eu

        font_dir=/var/www/onlyoffice/Data/custom-fonts
        config_file="$font_dir/chatterrow-japanese-fonts.conf"
        config_tmp="$font_dir/.chatterrow-japanese-fonts.conf.tmp"

        cat > "$config_tmp" <<EOF
<?xml version="1.0"?>
<!DOCTYPE fontconfig SYSTEM "urn:fontconfig:fonts.dtd">
<fontconfig>
  <dir>/var/www/onlyoffice/Data/custom-fonts</dir>
  <match target="pattern">
    <test name="family" qual="any" compare="eq"><string>游ゴシック Light</string></test>
    <edit name="family" mode="assign" binding="same"><string>Source Han Sans JP</string></edit>
    <edit name="weight" mode="assign" binding="same"><const>light</const></edit>
  </match>
  <match target="pattern">
    <test name="family" qual="any" compare="eq"><string>Yu Gothic Light</string></test>
    <edit name="family" mode="assign" binding="same"><string>Source Han Sans JP</string></edit>
    <edit name="weight" mode="assign" binding="same"><const>light</const></edit>
  </match>
  <alias binding="same">
    <family>游明朝</family>
    <prefer><family>Noto Serif CJK JP</family></prefer>
  </alias>
  <alias binding="same">
    <family>Yu Mincho</family>
    <prefer><family>Noto Serif CJK JP</family></prefer>
  </alias>
  <alias binding="same">
    <family>游ゴシック</family>
    <prefer><family>Source Han Sans JP</family></prefer>
  </alias>
  <alias binding="same">
    <family>游ゴシック Light</family>
    <prefer><family>Source Han Sans JP</family></prefer>
  </alias>
  <alias binding="same">
    <family>Yu Gothic</family>
    <prefer><family>Source Han Sans JP</family></prefer>
  </alias>
  <alias binding="same">
    <family>Yu Gothic Light</family>
    <prefer><family>Source Han Sans JP</family></prefer>
  </alias>
  <alias binding="same">
    <family>Meiryo</family>
    <prefer><family>Source Han Sans JP</family></prefer>
  </alias>
  <alias binding="same">
    <family>MS Gothic</family>
    <prefer><family>Source Han Sans JP</family></prefer>
  </alias>
  <alias binding="same">
    <family>MS Mincho</family>
    <prefer><family>Noto Serif CJK JP</family></prefer>
  </alias>
</fontconfig>
EOF
        if ! cmp -s "$config_tmp" "$config_file"; then
            mv -f "$config_tmp" "$config_file"
        else
            rm -f "$config_tmp"
        fi

        config_link=/etc/fonts/conf.d/65-chatterrow-japanese-fonts.conf
        current_link="$(readlink "$config_link" 2>/dev/null || true)"
        if [ "$current_link" != "$config_file" ]; then
            ln -sfn "$config_file" "$config_link"
        fi

        for legacy_font in \
            "$font_dir/NotoSansJP-VF.otf" \
            "$font_dir/NotoSerifJP-VF.otf" \
            "$font_dir/NotoSansJP-Light.otf" \
            "$font_dir/NotoSansJP-Regular.otf" \
            "$font_dir/NotoSansJP-Bold.otf" \
            "$font_dir/NotoSerifJP-Regular.otf" \
            "$font_dir/NotoSerifJP-Bold.otf"; do
            if [ -f "$legacy_font" ]; then
                rm -f "$legacy_font"
            fi
        done

        chmod 0644 "$font_dir"/SourceHanSansJP-Light.otf \
            "$font_dir"/SourceHanSansJP-Regular.otf \
            "$font_dir"/SourceHanSansJP-Bold.otf \
            "$font_dir"/NotoSerifCJKjp-Regular.otf \
            "$font_dir"/NotoSerifCJKjp-Bold.otf

        fc-cache -f
        fc-match "游明朝:lang=ja" | grep -q "NotoSerifCJKjp-Regular.otf"
        fc-match "游ゴシック Light:lang=ja" | grep -q "SourceHanSansJP-Light.otf"

        document_root=/var/www/onlyoffice/documentserver
        all_fonts=/var/www/onlyoffice/documentserver/server/FileConverter/bin/AllFonts.js
        font_selection=/var/www/onlyoffice/documentserver/server/FileConverter/bin/font_selection.bin
        all_fonts_web="$document_root/sdkjs/common/AllFonts.js"
        log_prefix="[chatterrow]"
        printf "%s Generating OnlyOffice font catalog...\n" "$log_prefix"
        export LD_LIBRARY_PATH="$document_root/server/FileConverter/bin:${LD_LIBRARY_PATH:-}"

        # macOS setup recreates the container on every run. The catalog files
        # live in that recreated filesystem, while custom fonts live in a named
        # volume, so the catalog must also be rebuilt on every run. allfontsgen
        # otherwise reuses existing outputs and silently skips new fonts.
        rm -f "$all_fonts" "$font_selection" "$all_fonts_web"
        "$document_root/server/tools/allfontsgen" \
            --input="$document_root/core-fonts" \
            --input="$document_root/../Data/custom-fonts" \
            --allfonts-web="$all_fonts_web" \
            --allfonts="$all_fonts" \
            --images="$document_root/sdkjs/common/Images" \
            --selection="$font_selection" \
            --output-web="$document_root/fonts" \
            --use-system="true" \
            --use-system-user-fonts="false"

        grep -q "Source Han Sans JP" "$all_fonts"
        grep -q "Noto Serif CJK JP" "$all_fonts"
        grep -a -q "SourceHanSansJP-Regular.otf" "$font_selection"
        grep -a -q "NotoSerifCJKjp-Regular.otf" "$font_selection"
    ' || die "Could not register Japanese fonts in $container_name"

    catalog_tmp_dir="$(mktemp -d "${TMPDIR:-/tmp}/chatterrow-onlyoffice-catalog.XXXXXX")" \
        || die "Could not create a temporary OnlyOffice catalog directory"
    if ! container copy \
        "${container_name}:/var/www/onlyoffice/documentserver/server/FileConverter/bin/font_selection.bin" \
        "$catalog_tmp_dir/font_selection.bin" \
        || ! container copy \
            "${container_name}:/var/www/onlyoffice/documentserver/server/FileConverter/bin/AllFonts.js" \
            "$catalog_tmp_dir/AllFonts.server.js" \
        || ! container copy \
            "${container_name}:/var/www/onlyoffice/documentserver/sdkjs/common/AllFonts.js" \
            "$catalog_tmp_dir/AllFonts.web.js"; then
        rm -f "$catalog_tmp_dir"/*
        rmdir "$catalog_tmp_dir" 2>/dev/null || true
        die "Could not copy the generated OnlyOffice font catalog out of $container_name"
    fi

    if ! php "$catalog_patcher" \
        --selection="$catalog_tmp_dir/font_selection.bin" \
        --all-fonts="$catalog_tmp_dir/AllFonts.server.js" \
        --all-fonts-web="$catalog_tmp_dir/AllFonts.web.js" \
        || ! container copy "$catalog_tmp_dir/font_selection.bin" \
            "${container_name}:/tmp/chatterrow-font_selection.bin" \
        || ! container copy "$catalog_tmp_dir/AllFonts.server.js" \
            "${container_name}:/tmp/chatterrow-AllFonts.server.js" \
        || ! container copy "$catalog_tmp_dir/AllFonts.web.js" \
            "${container_name}:/tmp/chatterrow-AllFonts.web.js"; then
        rm -f "$catalog_tmp_dir"/*
        rmdir "$catalog_tmp_dir" 2>/dev/null || true
        die "Could not patch the OnlyOffice Japanese font catalog"
    fi

    rm -f "$catalog_tmp_dir"/*
    rmdir "$catalog_tmp_dir"

    container exec "$container_name" sh -lc '
        set -eu

        document_root=/var/www/onlyoffice/documentserver
        converter_bin="$document_root/server/FileConverter/bin"
        all_fonts="$converter_bin/AllFonts.js"
        all_fonts_web="$document_root/sdkjs/common/AllFonts.js"
        font_selection="$converter_bin/font_selection.bin"
        font_dir=/var/www/onlyoffice/Data/custom-fonts

        cp /tmp/chatterrow-font_selection.bin "$font_selection"
        cp /tmp/chatterrow-AllFonts.server.js "$all_fonts"
        cp /tmp/chatterrow-AllFonts.web.js "$all_fonts_web"
        rm -f /tmp/chatterrow-font_selection.bin \
            /tmp/chatterrow-AllFonts.server.js \
            /tmp/chatterrow-AllFonts.web.js

        export LD_LIBRARY_PATH="$converter_bin:${LD_LIBRARY_PATH:-}"
        "$converter_bin/x2t" -create-js-cache

        grep -q "Yu Gothic" "$all_fonts"
        grep -q "Yu Mincho" "$all_fonts"
        grep -a -q "$font_dir/SourceHanSansJP-Regular.otf" "$font_selection"
        grep -a -q "$font_dir/NotoSerifCJKjp-Regular.otf" "$font_selection"

        chown -R ds:ds "$document_root/sdkjs" \
            "$converter_bin" \
            "$document_root/fonts"
        rm -f "$document_root"/fonts/*.gz \
            "$document_root/sdkjs/common/AllFonts.js.gz" \
            "$document_root"/sdkjs/common/Images/*.gz \
            "$document_root/sdkjs/slide/themes/themes.js.gz"
        printf "%s\n" source-han-sans-jp-2.005r-noto-serif-cjk-jp-2.003-v1 \
            > "$font_dir/.chatterrow-font-catalog-version"

        if pgrep -x supervisord >/dev/null; then
            supervisorctl restart ds:docservice
            supervisorctl restart ds:converter
        fi
        documentserver-flush-cache.sh
    ' || die "Could not activate the OnlyOffice Japanese font catalog in $container_name"
}

setup_macos_onlyoffice() {
    [[ "$(uname -m)" == "arm64" ]] || die "Apple Container requires an Apple silicon Mac"
    command -v container >/dev/null || die "Apple Container is required; install it from https://github.com/apple/container"
    command -v curl >/dev/null || die "curl is required"

    local macos_major app_dir env_file jwt_secret app_url app_scheme app_authority app_host app_port
    local app_port_explicit app_base_url app_server detected_server app_internal_url container_route_host
    local valet_available herd_available manager_url_ready artisan_url_ready artisan_port
    local -a container_run_args
    macos_major="$(sw_vers -productVersion | cut -d. -f1)"
    [[ "$macos_major" =~ ^[0-9]+$ && "$macos_major" -ge 26 ]] || die "Apple Container requires macOS 26 or newer"

    app_dir="$APP_DIR"
    [[ "$app_dir" == "/var/www/chatterrow" ]] && app_dir="$PWD"
    env_file="$app_dir/.env"

    [[ -f "$env_file" ]] || {
        [[ -f "$app_dir/.env.example" ]] || die "Could not find $app_dir/.env.example"
        cp "$app_dir/.env.example" "$env_file"
    }

    app_url="$(sed -n 's/^[[:space:]]*APP_URL=//p' "$env_file" | sed -n '1p')"
    app_url="${app_url#\"}"
    app_url="${app_url%\"}"
    case "$app_url" in
        http://*|https://*) ;;
        '') app_url="http://${MACOS_APP_HOST:-${DOMAIN:-$(basename "$app_dir").test}}" ;;
        *) die "APP_URL must use http or https for the macOS container setup: $app_url" ;;
    esac

    app_scheme="${app_url%%://*}"
    app_authority="${app_url#*://}"
    app_authority="${app_authority%%/*}"
    [[ -n "$app_authority" && "$app_authority" != *"@"* ]] || die "Invalid APP_URL authority: $app_url"

    app_port_explicit=0
    if [[ "$app_authority" =~ ^([^:]+):([0-9]+)$ ]]; then
        app_host="${BASH_REMATCH[1]}"
        app_port="${BASH_REMATCH[2]}"
        app_port_explicit=1
    elif [[ "$app_authority" != *":"* ]]; then
        app_host="$app_authority"
        [[ "$app_scheme" == "https" ]] && app_port=443 || app_port=80
    else
        die "IPv6 APP_URL values are not supported by the macOS container setup: $app_url"
    fi

    if [[ -n "$MACOS_APP_HOST" ]]; then
        app_host="$MACOS_APP_HOST"
        app_authority="$app_host"
        if [[ $app_port_explicit -eq 1 ]]; then
            app_authority="${app_host}:${app_port}"
        fi
    fi

    if ! valid_hostname "$app_host" && [[ "$app_host" != "localhost" ]] && ! valid_ipv4 "$app_host"; then
        die "Invalid macOS application hostname: $app_host"
    fi
    [[ "$app_port" =~ ^[0-9]+$ ]] && (( app_port >= 1 && app_port <= 65535 )) \
        || die "Invalid APP_URL port: $app_port"
    valid_ipv4 "$MACOS_HOST_REDIRECT_IP" || die "Invalid macOS host redirect IP: $MACOS_HOST_REDIRECT_IP"
    valid_hostname "$MACOS_HOST_DNS_NAME" || die "Invalid macOS host DNS name: $MACOS_HOST_DNS_NAME"
    valid_ipv4 "$MACOS_CONTAINER_DNS" || die "Invalid macOS container DNS server: $MACOS_CONTAINER_DNS"
    [[ "$MACOS_ONLYOFFICE_PORT" =~ ^[0-9]+$ ]] \
        && (( MACOS_ONLYOFFICE_PORT >= 1 && MACOS_ONLYOFFICE_PORT <= 65535 )) \
        || die "Invalid macOS OnlyOffice port: $MACOS_ONLYOFFICE_PORT"
    if [[ -n "$MACOS_ARTISAN_PORT" ]]; then
        [[ "$MACOS_ARTISAN_PORT" =~ ^[0-9]+$ ]] \
            && (( MACOS_ARTISAN_PORT >= 1 && MACOS_ARTISAN_PORT <= 65535 )) \
            || die "Invalid macOS artisan serve port: $MACOS_ARTISAN_PORT"
    fi

    case "$MACOS_APP_SERVER" in
        auto|valet|herd|artisan) ;;
        *) die "Invalid MACOS_APP_SERVER: $MACOS_APP_SERVER (use auto, valet, herd, or artisan)" ;;
    esac

    log "Starting Apple Container system service..."
    container system start

    # Apple Container redirects this documentation-range IP to the host's
    # loopback interface. Remove the old application-domain record first: it
    # overrides Valet/Herd resolution on macOS and would also break detection.
    if valid_hostname "$app_host" && [[ "$app_host" != "$MACOS_HOST_DNS_NAME" ]]; then
        sudo container system dns delete "$app_host" >/dev/null 2>&1 || true
    fi
    sudo container system dns delete "$MACOS_HOST_DNS_NAME" >/dev/null 2>&1 || true
    sudo container system dns create \
        "$MACOS_HOST_DNS_NAME" \
        --localhost "$MACOS_HOST_REDIRECT_IP" >/dev/null \
        || die "Could not configure Apple Container host redirection"

    app_base_url="${app_scheme}://${app_authority}"
    valet_available=0
    herd_available=0
    manager_url_ready=0
    artisan_url_ready=0
    if command -v valet >/dev/null 2>&1; then
        valet_available=1
    fi
    if command -v herd >/dev/null 2>&1; then
        herd_available=1
    fi

    if valid_hostname "$app_host" && endpoint_returns_ok "$app_base_url"; then
        manager_url_ready=1
    fi

    artisan_port=8000
    [[ $app_port_explicit -eq 0 ]] || artisan_port="$app_port"
    [[ -z "$MACOS_ARTISAN_PORT" ]] || artisan_port="$MACOS_ARTISAN_PORT"
    if endpoint_returns_ok "http://127.0.0.1:${artisan_port}"; then
        artisan_url_ready=1
    fi

    app_server="$MACOS_APP_SERVER"
    if [[ "$app_server" == "auto" ]]; then
        if [[ $manager_url_ready -eq 1 && $herd_available -eq 1 ]]; then
            app_server="herd"
        elif [[ $manager_url_ready -eq 1 && $valet_available -eq 1 ]]; then
            app_server="valet"
        elif [[ $manager_url_ready -eq 1 ]]; then
            app_server="router"
        elif [[ $artisan_url_ready -eq 1 ]]; then
            app_server="artisan"
        elif valid_hostname "$app_host" && [[ $herd_available -eq 1 ]]; then
            app_server="herd"
        elif valid_hostname "$app_host" && [[ $valet_available -eq 1 ]]; then
            app_server="valet"
        else
            app_server="artisan"
        fi
    fi

    case "$app_server" in
        valet)
            [[ $valet_available -eq 1 ]] || die "MACOS_APP_SERVER=valet but the valet command is unavailable"
            valid_hostname "$app_host" || die "Valet mode requires a hostname in APP_URL: $app_url"
            detected_server="Laravel Valet"
            container_route_host="$app_host"
            app_internal_url="$app_base_url"
            ;;
        herd)
            [[ $herd_available -eq 1 ]] || die "MACOS_APP_SERVER=herd but the herd command is unavailable"
            valid_hostname "$app_host" || die "Herd mode requires a hostname in APP_URL: $app_url"
            detected_server="Laravel Herd"
            container_route_host="$app_host"
            app_internal_url="$app_base_url"
            ;;
        router)
            detected_server="local domain web server"
            container_route_host="$app_host"
            app_internal_url="$app_base_url"
            ;;
        artisan)
            detected_server="php artisan serve"
            container_route_host="$MACOS_HOST_DNS_NAME"
            app_internal_url="http://${MACOS_HOST_DNS_NAME}:${artisan_port}"
            ;;
        *) die "Could not detect the macOS application server" ;;
    esac

    app_internal_url="${app_internal_url%/}"
    log "Detected macOS application server: $detected_server"
    log "OnlyOffice will reach the application at $app_internal_url"
    if [[ "$app_server" == "artisan" && $artisan_url_ready -eq 0 ]]; then
        warn "No response from 127.0.0.1:${artisan_port}; start 'php artisan serve --host=127.0.0.1 --port=${artisan_port}' before opening a preview"
    elif [[ "$app_server" != "artisan" && $manager_url_ready -eq 0 ]]; then
        warn "$detected_server was detected, but $app_base_url/up is not responding"
    fi

    jwt_secret="$(sed -n 's/^[[:space:]]*ONLYOFFICE_JWT_SECRET=//p' "$env_file" | sed -n '1p')"
    jwt_secret="${jwt_secret#\"}"
    jwt_secret="${jwt_secret%\"}"
    [[ "${#jwt_secret}" -ge 32 ]] || jwt_secret="$(openssl rand -hex 32)"

    container_run_args=(
        --detach
        --name "$ONLYOFFICE_CONTAINER_NAME"
        --arch arm64
        --cpus "$MACOS_ONLYOFFICE_CPUS"
        --memory "$MACOS_ONLYOFFICE_MEMORY"
        --shm-size "$MACOS_ONLYOFFICE_SHM_SIZE"
        --dns "$MACOS_CONTAINER_DNS"
        --publish "127.0.0.1:${MACOS_ONLYOFFICE_PORT}:80"
        --volume "${ONLYOFFICE_DATA_VOLUME}:/var/www/onlyoffice/Data"
        --volume "${ONLYOFFICE_LOGS_VOLUME}:/var/log/onlyoffice"
        --volume "${ONLYOFFICE_CACHE_VOLUME}:/var/lib/onlyoffice"
        --volume "${ONLYOFFICE_POSTGRES_VOLUME}:/var/lib/postgresql"
        --env "JWT_ENABLED=true"
        --env JWT_SECRET
        --env "JWT_HEADER=AuthorizationJwt"
        --env "JWT_IN_BODY=true"
        # setup_onlyoffice_japanese_fonts generates only the font catalog.
        # The image default also regenerates presentation themes, which can
        # stall indefinitely under Apple Container on arm64.
        --env "GENERATE_FONTS=false"
        --env "CHATTERROW_APP_HOST=${container_route_host}"
        --env "CHATTERROW_HOST_REDIRECT_IP=${MACOS_HOST_REDIRECT_IP}"
        --env "CHATTERROW_APP_INTERNAL_URL=${app_internal_url}"
        --env "CHATTERROW_CONTAINER_DNS=${MACOS_CONTAINER_DNS}"
        --entrypoint /bin/bash
    )

    log "Pulling the current $ONLYOFFICE_IMAGE image..."
    container image pull "$ONLYOFFICE_IMAGE" >/dev/null \
        || die "Could not pull $ONLYOFFICE_IMAGE"

    if container inspect "$ONLYOFFICE_CONTAINER_NAME" >/dev/null 2>&1; then
        warn "Recreating the macOS OnlyOffice container; named volumes will be preserved"
        # A stuck font/theme generator can prevent both exec and graceful stop.
        # Force-removing the container does not remove its named volumes.
        container delete --force "$ONLYOFFICE_CONTAINER_NAME" >/dev/null \
            || die "Could not remove $ONLYOFFICE_CONTAINER_NAME"
    fi

    log "Starting OnlyOffice DocumentServer with Apple Container..."
    JWT_SECRET="$jwt_secret" container run \
        "${container_run_args[@]}" \
        "$ONLYOFFICE_IMAGE" \
        -lc 'sed -i "/[[:space:]]${CHATTERROW_APP_HOST}\\([[:space:]]\\|$\\)/d" /etc/hosts; printf "%s %s\n" "$CHATTERROW_HOST_REDIRECT_IP" "$CHATTERROW_APP_HOST" >> /etc/hosts; exec /bin/bash /app/ds/run-document-server.sh' \
        >/dev/null || die "Could not start OnlyOffice DocumentServer"

    # Apply the mapping immediately as well as from the startup wrapper. This
    # repairs a running container whose generated /etc/hosts was replaced.
    container exec "$ONLYOFFICE_CONTAINER_NAME" sh -lc \
        'sed -i "/[[:space:]]${CHATTERROW_APP_HOST}\\([[:space:]]\\|$\\)/d" /etc/hosts; printf "%s %s\n" "$CHATTERROW_HOST_REDIRECT_IP" "$CHATTERROW_APP_HOST" >> /etc/hosts' \
        || die "Could not map $container_route_host inside the OnlyOffice container"
    container exec "$ONLYOFFICE_CONTAINER_NAME" getent hosts "$container_route_host" \
        | grep -Eq "^${MACOS_HOST_REDIRECT_IP}[[:space:]]" \
        || die "OnlyOffice did not resolve $container_route_host to $MACOS_HOST_REDIRECT_IP"
    container exec "$ONLYOFFICE_CONTAINER_NAME" awk -v "dns=$MACOS_CONTAINER_DNS" \
        '$1 == "nameserver" && $2 == dns { found = 1 } END { exit !found }' \
        /etc/resolv.conf \
        || die "OnlyOffice is not using DNS server $MACOS_CONTAINER_DNS"

    set_env ONLYOFFICE_ENABLED true "$env_file"
    set_env ONLYOFFICE_DOCUMENT_SERVER_URL "http://127.0.0.1:${MACOS_ONLYOFFICE_PORT}" "$env_file"
    set_env ONLYOFFICE_PUBLIC_URL "http://127.0.0.1:${MACOS_ONLYOFFICE_PORT}" "$env_file"
    set_env APP_ONLYOFFICE_INTERNAL_URL "$app_internal_url" "$env_file"
    set_env ONLYOFFICE_JWT_SECRET "$jwt_secret" "$env_file"
    set_env ONLYOFFICE_ALLOW_DOWNLOAD false "$env_file"
    set_env ONLYOFFICE_ALLOW_PRINT false "$env_file"

    for _ in {1..60}; do
        if curl -fsS "http://127.0.0.1:${MACOS_ONLYOFFICE_PORT}/healthcheck" 2>/dev/null | grep -Eq 'true|ok'; then
            setup_onlyoffice_japanese_fonts "$ONLYOFFICE_CONTAINER_NAME" "$app_dir"

            # Font catalog generation restarts the document and converter
            # services. Wait for DocumentServer to become ready again.
            for _ in {1..60}; do
                if curl -fsS "http://127.0.0.1:${MACOS_ONLYOFFICE_PORT}/healthcheck" 2>/dev/null | grep -Eq 'true|ok'; then
                    break
                fi
                sleep 2
            done
            curl -fsS "http://127.0.0.1:${MACOS_ONLYOFFICE_PORT}/healthcheck" 2>/dev/null \
                | grep -Eq 'true|ok' \
                || die "OnlyOffice did not recover after Japanese font generation"

            log "OnlyOffice DocumentServer is ready on http://127.0.0.1:${MACOS_ONLYOFFICE_PORT}"

            if container exec "$ONLYOFFICE_CONTAINER_NAME" \
                curl -fsS --max-time 5 "${app_internal_url}/up" >/dev/null; then
                log "OnlyOffice can reach the application at $app_internal_url"
            else
                warn "OnlyOffice cannot reach ${app_internal_url}/up; verify $detected_server is running"
            fi

            if [[ -f "$app_dir/artisan" ]] && command -v php >/dev/null; then
                (cd "$app_dir" && php artisan optimize:clear >/dev/null)
            fi

            log "Updated $env_file"
            return 0
        fi
        sleep 2
    done

    container logs "$ONLYOFFICE_CONTAINER_NAME" || true
    die "OnlyOffice health check failed on 127.0.0.1:${MACOS_ONLYOFFICE_PORT}"
}

prompt_required() {
    local prompt="$1" value=""

    while [[ -z "$value" ]]; do
        read -r -p "$prompt" value
    done
    printf '%s' "$value"
}

APP_DIR="${APP_DIR:-/var/www/chatterrow}"
REPO_URL="${REPO_URL:-git@github.com:askdkc/chatterrowrow.git}"
DOMAIN="${DOMAIN:-}"
EMAIL="${EMAIL:-}"
OFFICE_DOMAIN="${OFFICE_DOMAIN:-}"
DATABASE="${DATABASE:-}"
DB_NAME="${DB_NAME:-chatterrow}"
DB_USER="${DB_USER:-chatterrow}"
DB_PASSWORD="${DB_PASSWORD:-}"
NO_SSL=0
REVERB_SERVER_PORT=8081
ONLYOFFICE_PORT="${ONLYOFFICE_PORT:-8080}"
APP_INTERNAL_PORT=8090
ONLYOFFICE_IMAGE="${ONLYOFFICE_IMAGE:-onlyoffice/documentserver:latest}"
ONLYOFFICE_CONTAINER_NAME="${ONLYOFFICE_CONTAINER_NAME:-chatterrow-onlyoffice-documentserver}"
MACOS_ONLYOFFICE_PORT="${MACOS_ONLYOFFICE_PORT:-8086}"
MACOS_ONLYOFFICE_CPUS="${MACOS_ONLYOFFICE_CPUS:-4}"
MACOS_ONLYOFFICE_MEMORY="${MACOS_ONLYOFFICE_MEMORY:-4G}"
MACOS_ONLYOFFICE_SHM_SIZE="${MACOS_ONLYOFFICE_SHM_SIZE:-2G}"
MACOS_APP_HOST="${MACOS_APP_HOST:-}"
MACOS_APP_SERVER="${MACOS_APP_SERVER:-auto}"
MACOS_ARTISAN_PORT="${MACOS_ARTISAN_PORT:-}"
MACOS_HOST_REDIRECT_IP="${MACOS_HOST_REDIRECT_IP:-203.0.113.150}"
MACOS_HOST_DNS_NAME="${MACOS_HOST_DNS_NAME:-chatter-host.container.internal}"
MACOS_CONTAINER_DNS="${MACOS_CONTAINER_DNS:-1.1.1.1}"
ONLYOFFICE_DATA_VOLUME="${ONLYOFFICE_DATA_VOLUME:-chatterrow-onlyoffice-data}"
ONLYOFFICE_LOGS_VOLUME="${ONLYOFFICE_LOGS_VOLUME:-chatterrow-onlyoffice-logs}"
ONLYOFFICE_CACHE_VOLUME="${ONLYOFFICE_CACHE_VOLUME:-chatterrow-onlyoffice-cache}"
ONLYOFFICE_POSTGRES_VOLUME="${ONLYOFFICE_POSTGRES_VOLUME:-chatterrow-onlyoffice-postgresql}"

usage() {
    cat <<'EOF'
Usage: ./setup.sh [options]

Options:
  --domain <domain>          App domain, e.g. chat.example.com (prompted if omitted)
  --email <email>            Let's Encrypt registration / expiry mail
  --office-domain <domain>   OnlyOffice domain (default: office.<domain>)
  --database <driver>        App DB: sqlite or postgresql (prompted; default: sqlite)
  --db-name <name>           PostgreSQL database name (default: chatterrow)
  --db-user <name>           PostgreSQL role name (default: chatterrow)
  --db-password <password>   PostgreSQL password (default: securely generated)
  --app-dir <path>           App install path (default: /var/www/chatterrow)
  --repo <url>               Git repo to deploy (default: git@github.com:askdkc/chatterrowrow.git)
  --onlyoffice-image <image> OnlyOffice image pulled on each macOS run (default: onlyoffice/documentserver:latest)
  --no-ssl                   Skip Let's Encrypt (HTTP only, for testing)
  -h, --help                 Show this help

All options can also be supplied through same-named uppercase environment
variables, except --no-ssl. Non-interactive runs require --domain and
--database (or DOMAIN and DATABASE).

macOS Apple Container overrides:
  MACOS_APP_HOST              Application hostname override (default: APP_URL host)
  MACOS_APP_SERVER            auto, valet, herd, or artisan (default: auto)
  MACOS_ARTISAN_PORT          php artisan serve port (default: APP_URL port or 8000)
  MACOS_ONLYOFFICE_PORT       DocumentServer host port (default: 8086)
  MACOS_HOST_REDIRECT_IP      Container-to-host loopback IP (default: 203.0.113.150)
  MACOS_HOST_DNS_NAME         Private redirect record (default: chatter-host.container.internal)
  MACOS_CONTAINER_DNS         Container DNS server (default: 1.1.1.1)
  MACOS_ONLYOFFICE_CPUS       Container CPU count (default: 4)
  MACOS_ONLYOFFICE_MEMORY     Container memory limit (default: 4G)
  MACOS_ONLYOFFICE_SHM_SIZE   Container shared memory size (default: 2G)
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain|--email|--office-domain|--database|--db-name|--db-user|--db-password|--app-dir|--repo|--onlyoffice-image)
            [[ $# -ge 2 ]] || die "$1 requires a value"
            case "$1" in
                --domain)        DOMAIN="$2" ;;
                --email)         EMAIL="$2" ;;
                --office-domain) OFFICE_DOMAIN="$2" ;;
                --database)      DATABASE="$2" ;;
                --db-name)       DB_NAME="$2" ;;
                --db-user)       DB_USER="$2" ;;
                --db-password)   DB_PASSWORD="$2" ;;
                --app-dir)       APP_DIR="$2" ;;
                --repo)          REPO_URL="$2" ;;
                --onlyoffice-image) ONLYOFFICE_IMAGE="$2" ;;
            esac
            shift 2
            ;;
        --no-ssl) NO_SSL=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) die "Unknown option: $1 (see --help)" ;;
    esac
done

if [[ "$(uname -s)" == "Darwin" ]]; then
    setup_macos_onlyoffice
    exit 0
fi

if [[ -z "$DOMAIN" ]]; then
    [[ -t 0 ]] || die "--domain is required for non-interactive installation"
    DOMAIN="$(prompt_required 'Application domain (e.g. chat.example.com): ')"
fi

if [[ -z "$DATABASE" ]]; then
    [[ -t 0 ]] || die "--database is required for non-interactive installation"
    read -r -p "Application database [sqlite/postgresql] (default: sqlite): " DATABASE
    DATABASE="${DATABASE:-sqlite}"
fi

DATABASE="${DATABASE,,}"
case "$DATABASE" in
    sqlite) ;;
    pgsql|postgres|postgresql) DATABASE="pgsql" ;;
    *) die "Unsupported database: $DATABASE (use sqlite or postgresql)" ;;
esac

if [[ $NO_SSL -eq 0 && -z "$EMAIL" && -t 0 ]]; then
    read -r -p "Let's Encrypt email (optional): " EMAIL
fi

if [[ $NO_SSL -eq 0 ]]; then
    valid_hostname "$DOMAIN" || die "Invalid public domain: $DOMAIN"
else
    valid_local_hostname "$DOMAIN" || die "Invalid domain or local hostname: $DOMAIN"
    warn "--no-ssl selected; the app will use HTTP"
fi

OFFICE_DOMAIN="${OFFICE_DOMAIN:-office.${DOMAIN}}"
if [[ $NO_SSL -eq 0 ]]; then
    valid_hostname "$OFFICE_DOMAIN" || die "Invalid OnlyOffice domain: $OFFICE_DOMAIN"
else
    valid_local_hostname "$OFFICE_DOMAIN" || die "Invalid OnlyOffice domain: $OFFICE_DOMAIN"
fi

[[ -z "$EMAIL" || "$EMAIL" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || die "Invalid email: $EMAIL"
[[ "$DB_NAME" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || die "Invalid PostgreSQL database name: $DB_NAME"
[[ "$DB_USER" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || die "Invalid PostgreSQL role name: $DB_USER"
[[ "$DB_PASSWORD" != *$'\n'* && "$DB_PASSWORD" != *$'\r'* ]] || die "PostgreSQL password cannot contain newlines"
[[ "$DOMAIN" != "$OFFICE_DOMAIN" ]] || die "App and OnlyOffice domains must be different"
[[ ! "$DB_NAME" =~ ^(postgres|template0|template1)$ ]] || die "Reserved PostgreSQL database name: $DB_NAME"
[[ "$DB_USER" != "postgres" ]] || die "The PostgreSQL superuser cannot be used by the application"

APP_DIR="$(realpath -m -- "$APP_DIR")"
[[ "$APP_DIR" == /var/www/* && "$APP_DIR" != "/var/www/" ]] || die "--app-dir must be a child of /var/www"
[[ "$APP_DIR" != *$'\n'* && "$APP_DIR" != *$'\r'* && "$APP_DIR" != *[[:space:]]* ]] || die "--app-dir cannot contain whitespace"
[[ "$REPO_URL" != *$'\n'* && "$REPO_URL" != *$'\r'* ]] || die "--repo cannot contain newlines"

# ----------------------------------------------------------- preflight -----
command -v sudo >/dev/null || die "sudo is required"
[[ "$(id -u)" -ne 0 ]] || die "run as a regular sudo user, not root"
sudo -v || die "sudo authentication failed"

. /etc/os-release
case "$VERSION_ID" in
    24.04|26.04) ;;
    *) die "Unsupported Ubuntu version: $VERSION_ID (supported: 24.04, 26.04)" ;;
esac

ARCHITECTURE="$(dpkg --print-architecture)"
[[ "$ARCHITECTURE" == "amd64" ]] || die "ONLYOFFICE Document Server requires amd64; detected $ARCHITECTURE"
CPU_COUNT="$(getconf _NPROCESSORS_ONLN)"
MEMORY_MB="$(awk '/MemTotal:/ { print int($2 / 1024) }' /proc/meminfo)"
SWAP_MB="$(awk '/SwapTotal:/ { print int($2 / 1024) }' /proc/meminfo)"
AVAILABLE_DISK_MB="$(df -Pm /var | awk 'NR == 2 { print $4 }')"
[[ "$CPU_COUNT" =~ ^[0-9]+$ && "$CPU_COUNT" -ge 2 ]] || die "At least 2 CPU cores are required for ONLYOFFICE"
[[ "$MEMORY_MB" =~ ^[0-9]+$ && "$MEMORY_MB" -ge 2048 ]] || die "At least 2 GB RAM is required for ONLYOFFICE"
[[ "$AVAILABLE_DISK_MB" =~ ^[0-9]+$ && "$AVAILABLE_DISK_MB" -ge 40960 ]] || die "At least 40 GB free space under /var is required"
(( SWAP_MB >= 4096 )) || warn "ONLYOFFICE recommends at least 4 GB swap; detected ${SWAP_MB} MB"

DEPLOY_USER="${SUDO_USER:-${USER:-$(id -un)}}"
PUBLIC_SCHEME="https"
PUBLIC_PORT=443
[[ $NO_SSL -eq 0 ]] || { PUBLIC_SCHEME="http"; PUBLIC_PORT=80; }

log "Ubuntu $VERSION_ID ($VERSION_CODENAME) on $ARCHITECTURE"
log "Domain: $DOMAIN | OnlyOffice: $OFFICE_DOMAIN | Database: $DATABASE"

# --------------------------------------------------- base packages --------
log "Installing web, database, preview, SSL, and build packages..."
sudo apt-get update -y
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y curl gnupg ca-certificates lsb-release ubuntu-keyring

# ONLYOFFICE requires nginx 1.30+. Ubuntu 24.04 and 26.04 currently ship
# older builds, so use the signed official stable repository.
curl -fsSL https://nginx.org/keys/nginx_signing.key | gpg --dearmor | \
    sudo tee /usr/share/keyrings/nginx-archive-keyring.gpg >/dev/null
NGINX_KEY_FINGERPRINTS="$(gpg --no-default-keyring --keyring /usr/share/keyrings/nginx-archive-keyring.gpg --with-colons --fingerprint 2>/dev/null | awk -F: '$1 == "fpr" { print $10 }')"
grep -qx '573BFD6B3D8FBC641079A6ABABF5BD827BD9BF62' <<< "$NGINX_KEY_FINGERPRINTS" || die "Official nginx signing key fingerprint check failed"
echo "deb [signed-by=/usr/share/keyrings/nginx-archive-keyring.gpg] https://nginx.org/packages/ubuntu ${VERSION_CODENAME} nginx" | \
    sudo tee /etc/apt/sources.list.d/nginx.list >/dev/null
printf 'Package: *\nPin: origin nginx.org\nPin: release o=nginx\nPin-Priority: 900\n' | \
    sudo tee /etc/apt/preferences.d/99nginx >/dev/null
sudo apt-get update -y
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
    apt-transport-https ca-certificates curl gnupg lsb-release software-properties-common \
    git unzip zip rsync acl jq openssl \
    nginx supervisor certbot python3-certbot-nginx \
    postgresql postgresql-client postgresql-contrib \
    redis-server rabbitmq-server \
    libreoffice poppler-utils imagemagick ghostscript \
    fonts-dejavu-core fonts-liberation fonts-noto-cjk sqlite3

NGINX_VERSION="$(nginx -v 2>&1 | cut -d/ -f2)"
dpkg --compare-versions "$NGINX_VERSION" ge 1.30 || die "ONLYOFFICE requires nginx 1.30+; installed version is $NGINX_VERSION"

# Microsoft core fonts improve Office rendering but are not required to run.
echo "ttf-mscorefonts-installer msttcorefonts/accepted-mscorefonts-eula select true" | sudo debconf-set-selections
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y ttf-mscorefonts-installer || \
    warn "Microsoft core fonts could not be installed; continuing with open fonts"

# Japanese fonts are required for LibreOffice previews and the file viewer.
if fc-list :lang=ja 2>/dev/null | grep -q .; then
    log "Japanese fonts available: $(fc-list :lang=ja family 2>/dev/null | sort -u | paste -sd, - | cut -c1-160)"
else
    warn "No Japanese fonts detected; Office previews may render incorrectly"
fi

# --------------------------------------------------------- PHP 8.4+ --------
# Ubuntu 24.04 ships PHP 8.3, but the locked Symfony 8 dependencies require
# PHP 8.4.1 or newer. Ubuntu 26.04's distro PHP already satisfies this.
if [[ "$VERSION_ID" == "24.04" ]]; then
    log "Enabling the maintained PHP repository for PHP 8.4 on Ubuntu 24.04..."
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt-get update -y
    PHP_PACKAGE_PREFIX="php8.4"
else
    PHP_PACKAGE_PREFIX="php"
fi

log "Installing PHP 8.4+ and required extensions..."
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
    "${PHP_PACKAGE_PREFIX}-cli" "${PHP_PACKAGE_PREFIX}-fpm" "${PHP_PACKAGE_PREFIX}-common" \
    "${PHP_PACKAGE_PREFIX}-opcache" "${PHP_PACKAGE_PREFIX}-curl" "${PHP_PACKAGE_PREFIX}-mbstring" \
    "${PHP_PACKAGE_PREFIX}-xml" "${PHP_PACKAGE_PREFIX}-zip" "${PHP_PACKAGE_PREFIX}-bcmath" \
    "${PHP_PACKAGE_PREFIX}-intl" "${PHP_PACKAGE_PREFIX}-sqlite3" "${PHP_PACKAGE_PREFIX}-pgsql" \
    "${PHP_PACKAGE_PREFIX}-gd"

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
PHP_VERSION_ID="$(php -r 'echo PHP_VERSION_ID;')"
(( PHP_VERSION_ID >= 80401 )) || die "PHP 8.4.1 or newer is required; active CLI is $(php -r 'echo PHP_VERSION;')"
[[ -S "$PHP_FPM_SOCK" || -f "/lib/systemd/system/php${PHP_VER}-fpm.service" ]] || die "PHP-FPM for active PHP $PHP_VER was not installed"
log "PHP $PHP_VER active; PDO drivers: $(php -r 'echo implode(",", PDO::getAvailableDrivers());')"

# ------------------------------------------------------------- composer ----
if ! command -v composer >/dev/null; then
    log "Installing Composer with checksum verification..."
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$EXPECTED_CHECKSUM" == "$ACTUAL_CHECKSUM" ]] || die "Composer installer checksum mismatch"
    sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi
composer --version

# ---------------------------------------------------------- nodejs 22 ------
if ! command -v node >/dev/null || ! command -v npm >/dev/null || [[ "$(node -v | tr -d 'v' | cut -d. -f1)" -lt 22 ]]; then
    log "Installing Node.js 22 from NodeSource..."
    curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y nodejs
fi
node --version
npm --version

# ----------------------------------------------- PostgreSQL auto-tuning ----
log "Starting and tuning PostgreSQL for this host..."
sudo systemctl enable --now postgresql

mapfile -t PG_CLUSTERS < <(pg_lsclusters --no-header)
[[ "${#PG_CLUSTERS[@]}" -eq 1 ]] || die "Expected exactly one PostgreSQL cluster; found ${#PG_CLUSTERS[@]}. Remove unused clusters or configure PostgreSQL manually."
read -r PG_VERSION PG_CLUSTER PG_PORT PG_STATUS PG_OWNER PG_DATA PG_LOG <<< "${PG_CLUSTERS[0]}"
PG_SERVICE="postgresql@${PG_VERSION}-${PG_CLUSTER}"
[[ "$PG_STATUS" == "online" ]] || sudo systemctl start "$PG_SERVICE"

# Chatterrow, PHP, and OnlyOffice share the host, so PostgreSQL receives a
# conservative fraction rather than dedicated-database-server values.
SHARED_BUFFERS_MB="$(clamp $((MEMORY_MB / 5)) 128 8192)"
EFFECTIVE_CACHE_MB="$(clamp $((MEMORY_MB * 3 / 5)) 256 65536)"
MAINTENANCE_WORK_MB="$(clamp $((MEMORY_MB / 20)) 64 1024)"
AUTOVACUUM_WORK_MB="$(clamp $((MAINTENANCE_WORK_MB / 2)) 64 256)"

CPU_CONNECTIONS=$((CPU_COUNT * 25))
MEMORY_CONNECTIONS=$((MEMORY_MB / 16))
(( CPU_CONNECTIONS < MEMORY_CONNECTIONS )) && CONNECTION_CANDIDATE="$CPU_CONNECTIONS" || CONNECTION_CANDIDATE="$MEMORY_CONNECTIONS"
MAX_CONNECTIONS="$(clamp "$CONNECTION_CANDIDATE" 50 300)"

AVAILABLE_MEMORY_MB=$((MEMORY_MB - SHARED_BUFFERS_MB))
WORK_MEM_MB="$(clamp $((AVAILABLE_MEMORY_MB / (MAX_CONNECTIONS * 4))) 4 64)"
MAX_WORKER_PROCESSES="$(clamp "$CPU_COUNT" 4 32)"
MAX_PARALLEL_WORKERS="$(clamp $((CPU_COUNT / 2)) 1 16)"
MAX_PARALLEL_PER_GATHER="$(clamp $((CPU_COUNT / 4)) 1 4)"
MAX_PARALLEL_MAINTENANCE="$(clamp $((CPU_COUNT / 4)) 1 4)"
AUTOVACUUM_WORKERS="$(clamp $((CPU_COUNT / 2)) 3 10)"

PG_CONFIG="$(sudo -u postgres psql -p "$PG_PORT" -Atqc 'SHOW config_file')"
[[ -n "$PG_CONFIG" && -f "$PG_CONFIG" ]] || die "Could not locate postgresql.conf"
PG_CONFIG_DIR="$(dirname "$PG_CONFIG")"
sudo install -d -o postgres -g postgres -m 0750 "$PG_CONFIG_DIR/conf.d"

if ! sudo grep -Eq "^[[:space:]]*include_dir[[:space:]]*=[[:space:]]*'conf.d'" "$PG_CONFIG"; then
    printf "\n# Managed by chatterrow setup.sh\ninclude_dir = 'conf.d'\n" | sudo tee -a "$PG_CONFIG" >/dev/null
fi

sudo tee "$PG_CONFIG_DIR/conf.d/99-chatterrow-tuning.conf" >/dev/null <<POSTGRES
# Managed by chatterrow/setup.sh. Re-running setup recalculates these values.
# Detected resources: ${CPU_COUNT} CPU(s), ${MEMORY_MB} MB RAM.
shared_buffers = '${SHARED_BUFFERS_MB}MB'
effective_cache_size = '${EFFECTIVE_CACHE_MB}MB'
maintenance_work_mem = '${MAINTENANCE_WORK_MB}MB'
autovacuum_work_mem = '${AUTOVACUUM_WORK_MB}MB'
work_mem = '${WORK_MEM_MB}MB'
max_connections = ${MAX_CONNECTIONS}
max_worker_processes = ${MAX_WORKER_PROCESSES}
max_parallel_workers = ${MAX_PARALLEL_WORKERS}
max_parallel_workers_per_gather = ${MAX_PARALLEL_PER_GATHER}
max_parallel_maintenance_workers = ${MAX_PARALLEL_MAINTENANCE}
autovacuum_max_workers = ${AUTOVACUUM_WORKERS}
checkpoint_completion_target = 0.9
min_wal_size = '1GB'
max_wal_size = '4GB'
wal_compression = on
huge_pages = try
POSTGRES
sudo chown postgres:postgres "$PG_CONFIG_DIR/conf.d/99-chatterrow-tuning.conf"
sudo chmod 0644 "$PG_CONFIG_DIR/conf.d/99-chatterrow-tuning.conf"
sudo -u postgres "/usr/lib/postgresql/${PG_VERSION}/bin/postgres" -C shared_buffers -D "$PG_DATA" -c "config_file=$PG_CONFIG" >/dev/null || \
    die "Generated PostgreSQL configuration is invalid; inspect $PG_CONFIG_DIR/conf.d/99-chatterrow-tuning.conf"
sudo systemctl restart "$PG_SERVICE"
sudo -u postgres pg_isready -p "$PG_PORT" -q || die "PostgreSQL did not become ready after tuning"
ACTUAL_SHARED_BUFFERS="$(sudo -u postgres psql -p "$PG_PORT" -Atqc 'SHOW shared_buffers')"
ACTUAL_MAX_CONNECTIONS="$(sudo -u postgres psql -p "$PG_PORT" -Atqc 'SHOW max_connections')"
[[ "$ACTUAL_MAX_CONNECTIONS" == "$MAX_CONNECTIONS" ]] || die "PostgreSQL tuning was not loaded by the selected cluster"
log "PostgreSQL tuned: shared_buffers=${ACTUAL_SHARED_BUFFERS}, work_mem=${WORK_MEM_MB}MB, max_connections=${MAX_CONNECTIONS}, workers=${MAX_WORKER_PROCESSES}"

if [[ "$DATABASE" == "pgsql" ]]; then
    DB_CREDENTIAL_FILE="/etc/chatterrow/database-password"
    if [[ -z "$DB_PASSWORD" ]] && sudo test -r "$DB_CREDENTIAL_FILE"; then
        DB_PASSWORD="$(sudo sh -c "tr -d '\\r\\n' < '$DB_CREDENTIAL_FILE'")"
    fi
    if [[ -z "$DB_PASSWORD" && -f "$APP_DIR/.env" ]]; then
        EXISTING_DB_PASSWORD="$(sed -n 's/^DB_PASSWORD=//p' "$APP_DIR/.env" | sed -n '1p')"
        EXISTING_DB_PASSWORD="${EXISTING_DB_PASSWORD#\"}"
        EXISTING_DB_PASSWORD="${EXISTING_DB_PASSWORD%\"}"
        [[ "$EXISTING_DB_PASSWORD" =~ ^[A-Fa-f0-9]{64}$ ]] && DB_PASSWORD="$EXISTING_DB_PASSWORD"
    fi
    if [[ -z "$DB_PASSWORD" && -t 0 ]]; then
        read -r -s -p "PostgreSQL password (leave blank to generate): " DB_PASSWORD
        printf '\n'
    fi
    [[ -n "$DB_PASSWORD" ]] || DB_PASSWORD="$(openssl rand -hex 32)"
    sudo install -d -o root -g root -m 0700 /etc/chatterrow
    printf '%s\n' "$DB_PASSWORD" | sudo tee "$DB_CREDENTIAL_FILE" >/dev/null
    sudo chown root:root "$DB_CREDENTIAL_FILE"
    sudo chmod 0600 "$DB_CREDENTIAL_FILE"

    ROLE_IS_PRIVILEGED="$(sudo -u postgres psql -p "$PG_PORT" --set=db_user="$DB_USER" -Atq postgres <<'SQL'
SELECT EXISTS (
    SELECT 1 FROM pg_roles
    WHERE rolname = :'db_user'
      AND (rolsuper OR rolcreatedb OR rolcreaterole OR rolreplication OR rolbypassrls)
);
SQL
)"
    [[ "$ROLE_IS_PRIVILEGED" != "t" ]] || die "Refusing to use privileged PostgreSQL role: $DB_USER"

    log "Creating/updating PostgreSQL role '$DB_USER' and database '$DB_NAME'..."
    sudo -u postgres env CHATTERROW_DB_PASSWORD="$DB_PASSWORD" psql \
        -p "$PG_PORT" \
        --set=ON_ERROR_STOP=1 \
        --set=db_name="$DB_NAME" \
        --set=db_user="$DB_USER" \
        postgres <<'SQL'
\getenv db_password CHATTERROW_DB_PASSWORD
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'db_user', :'db_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'db_user') \gexec
SELECT format('ALTER ROLE %I WITH LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS', :'db_user', :'db_password') \gexec
SELECT format('CREATE DATABASE %I OWNER %I ENCODING %L TEMPLATE template0', :'db_name', :'db_user', 'UTF8')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'db_name') \gexec
SELECT format('ALTER DATABASE %I OWNER TO %I', :'db_name', :'db_user') \gexec
SQL
fi

# ---------------------------------------------------- ONLYOFFICE Docs ------
if dpkg-query -W -f='${db:Status-Status}' onlyoffice-documentserver 2>/dev/null | grep -qx 'installed'; then
    log "ONLYOFFICE Document Server already installed"
    EXISTING_ONLYOFFICE_SECRET="$(sudo jq -r '.services.CoAuthoring.secret.session.string // empty' /etc/onlyoffice/documentserver/local.json 2>/dev/null || true)"
    ONLYOFFICE_JWT_ENABLED="$(sudo jq -r '(.services.CoAuthoring.token.enable.browser == true) and (.services.CoAuthoring.token.enable.request.inbox == true) and (.services.CoAuthoring.token.enable.request.outbox == true)' /etc/onlyoffice/documentserver/local.json 2>/dev/null || true)"
    [[ -n "$EXISTING_ONLYOFFICE_SECRET" ]] || die "Existing ONLYOFFICE installation has no readable JWT secret"
    [[ "$ONLYOFFICE_JWT_ENABLED" == "true" ]] || die "Existing ONLYOFFICE installation does not have JWT enabled for browser/inbox/outbox"
    ONLYOFFICE_JWT_SECRET="$EXISTING_ONLYOFFICE_SECRET"
else
    log "Installing ONLYOFFICE Document Server on port $ONLYOFFICE_PORT with JWT enabled..."
    ONLYOFFICE_JWT_SECRET="${ONLYOFFICE_JWT_SECRET:-$(openssl rand -hex 24)}"
    curl -fsSL https://download.onlyoffice.com/GPG-KEY-ONLYOFFICE | \
        sudo gpg --batch --yes --dearmor -o /usr/share/keyrings/onlyoffice.gpg
    echo "deb [signed-by=/usr/share/keyrings/onlyoffice.gpg] https://download.onlyoffice.com/repo/debian squeeze main" | \
        sudo tee /etc/apt/sources.list.d/onlyoffice.list >/dev/null
    echo "onlyoffice-documentserver onlyoffice/ds-port select $ONLYOFFICE_PORT" | sudo debconf-set-selections
    echo "onlyoffice-documentserver onlyoffice/jwt-enabled boolean true" | sudo debconf-set-selections
    echo "onlyoffice-documentserver onlyoffice/jwt-secret password $ONLYOFFICE_JWT_SECRET" | sudo debconf-set-selections
    sudo apt-get update -y
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y onlyoffice-documentserver
fi

ONLYOFFICE_READY=0
for _ in {1..30}; do
    if curl -fsS "http://127.0.0.1:${ONLYOFFICE_PORT}/healthcheck" 2>/dev/null | grep -q 'true'; then
        ONLYOFFICE_READY=1
        break
    fi
    sleep 2
done
[[ $ONLYOFFICE_READY -eq 1 ]] || die "ONLYOFFICE health check failed on 127.0.0.1:$ONLYOFFICE_PORT"

# --------------------------------------------------------- app deploy ------
log "Deploying chatterrow into $APP_DIR..."
sudo mkdir -p "$APP_DIR"
sudo chown "$DEPLOY_USER":"$DEPLOY_USER" "$APP_DIR"
if [[ -d "$APP_DIR/.git" ]]; then
    log "Repository already present; pulling with fast-forward only"
    git -C "$APP_DIR" pull --ff-only
else
    GIT_SSH_COMMAND="ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new" git clone "$REPO_URL" "$APP_DIR" || {
        warn "SSH clone failed; retrying over HTTPS"
        HTTPS_REPO="${REPO_URL/git@github.com:/https:\/\/github.com\/}"
        git clone "$HTTPS_REPO" "$APP_DIR"
    }
fi

cd "$APP_DIR"

# Keep both deployment commands and web/worker processes able to update
# Laravel runtime paths across repeat deployments.
for RUNTIME_DIR in storage bootstrap/cache database; do
    [[ ! -d "$RUNTIME_DIR" ]] || sudo setfacl -R -m "u:${DEPLOY_USER}:rwx,u:www-data:rwx" -m "d:u:${DEPLOY_USER}:rwx,d:u:www-data:rwx" "$RUNTIME_DIR"
done

log "Installing production PHP dependencies..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

log "Preparing production environment..."
[[ -f .env ]] || cp .env.example .env
set_env APP_NAME chatterrow
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$PUBLIC_SCHEME://$DOMAIN"
set_env LOG_LEVEL warning
set_env BROADCAST_CONNECTION reverb
set_env QUEUE_CONNECTION database
set_env CACHE_STORE database

if [[ "$DATABASE" == "sqlite" ]]; then
    set_env DB_CONNECTION sqlite
    set_env DB_URL ''
    set_env DB_HOST ''
    set_env DB_PORT ''
    set_env DB_DATABASE "$APP_DIR/database/database.sqlite"
    set_env DB_USERNAME ''
    set_env DB_PASSWORD ''
    mkdir -p database
    touch database/database.sqlite
else
    set_env DB_CONNECTION pgsql
    set_env DB_URL ''
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT "$PG_PORT"
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env DB_PASSWORD "$(dotenv_quote "$DB_PASSWORD")"
fi

CURRENT_REVERB_KEY="$(sed -n 's/^REVERB_APP_KEY=//p' .env | sed -n '1p')"
CURRENT_REVERB_SECRET="$(sed -n 's/^REVERB_APP_SECRET=//p' .env | sed -n '1p')"
set_env REVERB_APP_ID chatterrow
set_env REVERB_APP_KEY "${CURRENT_REVERB_KEY:-$(openssl rand -hex 16)}"
set_env REVERB_APP_SECRET "${CURRENT_REVERB_SECRET:-$(openssl rand -hex 24)}"
set_env REVERB_HOST "$DOMAIN"
set_env REVERB_PORT "$PUBLIC_PORT"
set_env REVERB_SCHEME "$PUBLIC_SCHEME"
set_env REVERB_SERVER_HOST 127.0.0.1
set_env REVERB_SERVER_PORT "$REVERB_SERVER_PORT"
set_env REVERB_ALLOWED_ORIGINS "$DOMAIN"

set_env VITE_REVERB_APP_KEY '"${REVERB_APP_KEY}"'
set_env VITE_REVERB_HOST '"${REVERB_HOST}"'
set_env VITE_REVERB_PORT '"${REVERB_PORT}"'
set_env VITE_REVERB_SCHEME '"${REVERB_SCHEME}"'

set_env ONLYOFFICE_ENABLED true
set_env ONLYOFFICE_DOCUMENT_SERVER_URL "$PUBLIC_SCHEME://$OFFICE_DOMAIN"
set_env ONLYOFFICE_PUBLIC_URL "$PUBLIC_SCHEME://$OFFICE_DOMAIN"
set_env APP_ONLYOFFICE_INTERNAL_URL "http://127.0.0.1:$APP_INTERNAL_PORT"
set_env ONLYOFFICE_JWT_SECRET "$(dotenv_quote "$ONLYOFFICE_JWT_SECRET")"
set_env ONLYOFFICE_ALLOW_DOWNLOAD true
set_env ONLYOFFICE_ALLOW_PRINT true

CURRENT_APP_KEY="$(sed -n 's/^[[:space:]]*APP_KEY=//p' .env | sed -n '1p')"
CURRENT_APP_KEY="${CURRENT_APP_KEY#\"}"
CURRENT_APP_KEY="${CURRENT_APP_KEY%\"}"
[[ -n "$CURRENT_APP_KEY" ]] || php artisan key:generate --force
php artisan optimize:clear

log "Building frontend assets..."
npm ci --no-audit --no-fund
npm run build

log "Running database migrations..."
php artisan migrate --force

log "Setting application permissions..."
sudo chown -R "$DEPLOY_USER":www-data "$APP_DIR"
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo setfacl -R -m "u:${DEPLOY_USER}:rwx,u:www-data:rwx" -m "d:u:${DEPLOY_USER}:rwx,d:u:www-data:rwx" storage bootstrap/cache
sudo chown "$DEPLOY_USER":www-data .env
sudo chmod 0640 .env
if [[ "$DATABASE" == "sqlite" ]]; then
    sudo chown www-data:www-data database database/database.sqlite
    sudo chmod 0770 database
    sudo chmod 0660 database/database.sqlite
    sudo setfacl -R -m "u:${DEPLOY_USER}:rwx,u:www-data:rwx" -m "d:u:${DEPLOY_USER}:rwx,d:u:www-data:rwx" database
fi

# --------------------------------------------------------- nginx conf ------
log "Generating nginx virtual hosts..."
sudo install -d -m 0755 /etc/nginx/sites-available /etc/nginx/sites-enabled
printf 'include /etc/nginx/sites-enabled/*;\n' | sudo tee /etc/nginx/conf.d/00-sites-enabled.conf >/dev/null

PRESERVE_NGINX_CONFIG=0
if [[ $NO_SSL -eq 0 && -d "/etc/letsencrypt/live/$DOMAIN" && -f /etc/nginx/sites-available/chatterrow && -f /etc/nginx/sites-available/onlyoffice ]]; then
    PRESERVE_NGINX_CONFIG=1
    log "Existing TLS-enabled nginx configuration detected; preserving it during redeployment"
fi

NGINX_BACKUP_DIR=""
if [[ $NO_SSL -eq 0 && $PRESERVE_NGINX_CONFIG -eq 0 ]]; then
    NGINX_BACKUP_DIR="$(mktemp -d)"
    [[ ! -f /etc/nginx/sites-available/chatterrow ]] || sudo cp -a /etc/nginx/sites-available/chatterrow "$NGINX_BACKUP_DIR/chatterrow"
    [[ ! -f /etc/nginx/sites-available/onlyoffice ]] || sudo cp -a /etc/nginx/sites-available/onlyoffice "$NGINX_BACKUP_DIR/onlyoffice"
fi

if [[ $PRESERVE_NGINX_CONFIG -eq 0 ]]; then
sudo tee /etc/nginx/sites-available/chatterrow >/dev/null <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    return 444;
}

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

    # Reverb's WebSocket and event API paths.
    location ~ ^/(app|apps)/ {
        proxy_pass http://127.0.0.1:${REVERB_SERVER_PORT};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$http_host;
        proxy_set_header Scheme \$scheme;
        proxy_set_header SERVER_PORT \$server_port;
        proxy_set_header REMOTE_ADDR \$remote_addr;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 120s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# Internal route used by ONLYOFFICE to retrieve temporary signed files.
server {
    listen 127.0.0.1:${APP_INTERNAL_PORT};
    server_name localhost;
    root ${APP_DIR}/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        internal;
    }

    location ~ /\. {
        deny all;
    }
}
NGINX

sudo tee /etc/nginx/sites-available/onlyoffice >/dev/null <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${OFFICE_DOMAIN};
    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:${ONLYOFFICE_PORT};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 600s;
    }
}
NGINX
fi

sudo ln -sfn /etc/nginx/sites-available/chatterrow /etc/nginx/sites-enabled/chatterrow
sudo ln -sfn /etc/nginx/sites-available/onlyoffice /etc/nginx/sites-enabled/onlyoffice
sudo rm -f /etc/nginx/sites-enabled/default
sudo rm -f /etc/nginx/conf.d/default.conf
sudo nginx -t
sudo systemctl enable --now nginx
sudo systemctl reload nginx

# ---------------------------------------------------- supervisor conf ------
log "Generating queue, Reverb, and scheduler process definitions..."
sudo tee /etc/supervisor/conf.d/chatterrow-queue.conf >/dev/null <<SUPERVISOR
[program:chatterrow-queue]
directory=${APP_DIR}
command=php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3700
stdout_logfile=/var/log/chatterrow-queue.log
stderr_logfile=/var/log/chatterrow-queue-error.log
SUPERVISOR

sudo tee /etc/supervisor/conf.d/chatterrow-reverb.conf >/dev/null <<SUPERVISOR
[program:chatterrow-reverb]
directory=${APP_DIR}
command=php artisan reverb:start --host=127.0.0.1 --port=${REVERB_SERVER_PORT}
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=30
stdout_logfile=/var/log/chatterrow-reverb.log
stderr_logfile=/var/log/chatterrow-reverb-error.log
SUPERVISOR

sudo tee /etc/supervisor/conf.d/chatterrow-schedule.conf >/dev/null <<SUPERVISOR
[program:chatterrow-schedule]
directory=${APP_DIR}
command=php artisan schedule:work
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=60
stdout_logfile=/var/log/chatterrow-schedule.log
stderr_logfile=/var/log/chatterrow-schedule-error.log
SUPERVISOR

sudo systemctl enable --now "php${PHP_VER}-fpm" supervisor redis-server rabbitmq-server
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart chatterrow-queue chatterrow-reverb chatterrow-schedule

# ------------------------------------------------- Let's Encrypt SSL -------
if [[ $NO_SSL -eq 1 ]]; then
    warn "Skipping Let's Encrypt; HTTP-only deployment requested"
else
    log "Requesting Let's Encrypt certificate for $DOMAIN and $OFFICE_DOMAIN..."
    CERTBOT_ARGS=(--nginx -d "$DOMAIN" -d "$OFFICE_DOMAIN" --redirect --non-interactive --agree-tos)
    if [[ -n "$EMAIL" ]]; then
        CERTBOT_ARGS+=(--email "$EMAIL")
    else
        CERTBOT_ARGS+=(--register-unsafely-without-email)
    fi

    if sudo certbot "${CERTBOT_ARGS[@]}"; then
        sudo install -d -m 0755 /etc/letsencrypt/renewal-hooks/deploy
        printf '#!/usr/bin/env bash\nsystemctl reload nginx\n' | \
            sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-nginx >/dev/null
        sudo chmod 0755 /etc/letsencrypt/renewal-hooks/deploy/reload-nginx
        sudo systemctl enable --now certbot.timer
        sudo certbot renew --dry-run --cert-name "$DOMAIN" || warn "Certbot dry-run renewal failed; inspect /var/log/letsencrypt before production use"
        log "SSL installed; certbot.timer and nginx reload hook enabled"
    else
        if [[ -n "$NGINX_BACKUP_DIR" && -f "$NGINX_BACKUP_DIR/chatterrow" && -f "$NGINX_BACKUP_DIR/onlyoffice" ]]; then
            sudo cp -a "$NGINX_BACKUP_DIR/chatterrow" /etc/nginx/sites-available/chatterrow
            sudo cp -a "$NGINX_BACKUP_DIR/onlyoffice" /etc/nginx/sites-available/onlyoffice
            sudo nginx -t && sudo systemctl reload nginx
        fi
        die "Certbot failed. Confirm DNS A/AAAA records for $DOMAIN and $OFFICE_DOMAIN, then rerun setup."
    fi
fi
[[ -z "$NGINX_BACKUP_DIR" ]] || rm -rf "$NGINX_BACKUP_DIR"

# ------------------------------------------------------------ verify -------
log "Verifying services..."
sudo nginx -t
sudo -u postgres pg_isready -p "$PG_PORT"
sudo supervisorctl status

if [[ $NO_SSL -eq 0 && -d "/etc/letsencrypt/live/$DOMAIN" ]]; then
    curl --resolve "$DOMAIN:443:127.0.0.1" -fsS -o /dev/null "https://$DOMAIN/up" || \
        die "Application HTTPS health check failed"
    log "Application health check passed over HTTPS"
else
    curl -H "Host: $DOMAIN" -fsS -o /dev/null "http://127.0.0.1/up" || \
        die "Application HTTP health check failed"
    log "Application health check passed over HTTP"
fi

log "Provisioning complete."
log "App:        $PUBLIC_SCHEME://$DOMAIN (first user: /register)"
log "OnlyOffice: $PUBLIC_SCHEME://$OFFICE_DOMAIN"
log "Database:   $DATABASE"
log "PostgreSQL: $PG_CONFIG_DIR/conf.d/99-chatterrow-tuning.conf"
log "Processes:  sudo supervisorctl status"
log "Logs:       sudo tail -f /var/log/chatterrow-*.log"
