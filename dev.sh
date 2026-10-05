#!/usr/bin/env bash
# Local environment of CommonSight (doc/DEVELOPMENT.md).
#
#   ./dev.sh up [port]      build and start the environment (port remembered in .env, default 8090)
#   ./dev.sh down           stop it
#   ./dev.sh build          build the installation package (build/commonsight-<release>.tar.gz)
#   ./dev.sh deploy         build the package and install it as a new release in the local environment
#   ./dev.sh update-data    fetch all layers now
#   ./dev.sh update-tiles   build the tile archive now
#   ./dev.sh status         state of all layers
#   ./dev.sh test           all checks and tests
#   ./dev.sh logs           follow the logs of the services

set -euo pipefail

readonly ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly RUN="$ROOT/run"
readonly APP="$RUN/commonsight"
readonly WEB="$RUN/public_html"
readonly KEEP_RELEASES=5
# Paths inside the containers.
readonly HOME_IN=/home/commonsight
readonly FETCHER="$HOME_IN/commonsight/current/bin/fetcher.php"
# Placeholders of the templates in install/ for the local environment.
readonly PLACEHOLDERS=(-e "s#@PHP@#/usr/bin/php#g" -e "s#@APP@#$HOME_IN/commonsight#g" -e "s#@WEBROOT@#$HOME_IN/public_html#g")

export CS_UID="${CS_UID:-$(id -u)}" CS_GID="${CS_GID:-$(id -g)}"

# Port of the web server: from .env (written by "up <port>"), otherwise 8090.
port() { sed -n 's/^CS_HTTP_PORT=//p' "$ROOT/.env" 2>/dev/null | grep . || echo 8090; }

log() { printf '→ %s\n' "$*"; }
fail() { printf '✗ %s\n' "$*" >&2; exit 1; }

compose() { docker compose --project-directory "$ROOT" -f "$ROOT/compose.yaml" "$@"; }
tool() { compose run --rm -T "$@"; }
as_account() { compose exec -T -u commonsight "$@"; }

prepare_account() {
    mkdir -p "$APP"/{releases,state,cache,locks,logs,tiles-work} "$WEB"/{api,data/v1,tiles,map,app}
    [[ -f "$APP/config.php" ]] || { sed "${PLACEHOLDERS[@]}" "$ROOT/install/config.example.php" > "$APP/config.php"; log "config.php created"; }
}

cmd_up() {
    if [[ -n "${1:-}" ]]; then
        [[ "$1" =~ ^[0-9]+$ ]] || fail "Port must be a number: $1"
        printf 'CS_HTTP_PORT=%s\n' "$1" > "$ROOT/.env"
    fi
    prepare_account
    compose up -d --build
    log "Running: http://localhost:$(port)/ (deploy first: ./dev.sh deploy)"
}

cmd_down() { compose down; }

build_backend() {
    log "Backend: dependencies, master data"
    tool composer install --no-interaction --no-progress --quiet
    tool composer build-contract >/dev/null
}

build_frontend() {
    log "Frontend: Build"
    install_frontend_dependencies
    tool node npm run build >/dev/null
}

# npm ci when the dependencies are missing or the lockfile changed since they were installed.
install_frontend_dependencies() {
    if [[ ! -d "$ROOT/frontend/node_modules" || "$ROOT/frontend/package-lock.json" -nt "$ROOT/frontend/node_modules/.package-lock.json" ]]; then
        tool node npm ci --no-audit --no-fund --loglevel=error
    fi
}

# What a release needs of each plugin: its PHP code (backend/) and its master data (data/); no tests, tools or texts.
copy_plugins() {
    local target="$1" plugin part dir
    mkdir -p "$target"
    # plugins/<group>/<id>/ (groups: layers, news, providers) keeps its place in the release.
    for plugin in "$ROOT"/plugins/*/*/; do
        dir="$target/$(basename "$(dirname "$plugin")")/$(basename "$plugin")"
        for part in backend data; do
            [[ -d "$plugin$part" ]] || continue
            mkdir -p "$dir"
            cp -a "$plugin$part" "$dir/"
        done
    done
}

# Installation package: app/ (to <APP>/releases/<release>/), web/ (to the webroot), templates for configuration and cron.
cmd_build() {
    # The commit in the name only if the package is exactly that commit; with local changes it says so ("-dirty").
    local commit
    commit="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo nogit)"
    [[ -z "$(git -C "$ROOT" status --porcelain 2>/dev/null)" ]] || commit="$commit-dirty"
    RELEASE="$(date -u +%Y%m%d%H%M%S)-$commit"
    PACKAGE="$ROOT/build/commonsight-$RELEASE"
    build_backend
    build_frontend
    [[ -d "$ROOT/map-assets/glyphs" ]] || { log "Downloading the glyphs of the map"; "$ROOT/tools/tiles/fetch-map-assets.sh"; }

    log "Assembling package $RELEASE"
    rm -rf "$ROOT"/build/commonsight-* && mkdir -p "$PACKAGE/app/bin" "$PACKAGE/web/api" "$PACKAGE/web/app" "$PACKAGE/web/licenses"
    cp -a "$ROOT"/backend/{src,bin,public,generated,composer.json,composer.lock} "$PACKAGE/app/"
    copy_plugins "$PACKAGE/app/plugins"
    # In the release the plugins lie in app/plugins/ instead of ../plugins/ next to backend/.
    sed -i 's#"\.\./plugins/#"plugins/#g' "$PACKAGE/app/composer.json"
    tool -w "/src/build/commonsight-$RELEASE/app" composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
    # build-contract.php needs contract/ and the plugin sources; it runs only at build time.
    rm -f "$PACKAGE/app/composer.json" "$PACKAGE/app/composer.lock" "$PACKAGE/app/bin/build-contract.php"
    cp "$ROOT/tools/tiles/update-tiles.sh" "$PACKAGE/app/bin/"
    cp -a "$ROOT/frontend/dist" "$PACKAGE/web/app/$RELEASE"
    # Colours and logo of the operator (ACCESS-AND-BRANDING B-D3): defaults beside web/, copied without overwriting.
    mv "$PACKAGE/web/app/$RELEASE/custom" "$PACKAGE/custom"
    # All license texts in one place: own code, third-party parts, libraries of the bundle.
    cp "$ROOT/LICENSE" "$ROOT"/LICENSES/* "$PACKAGE/web/licenses/"
    mv "$PACKAGE/web/app/$RELEASE/licenses/THIRD-PARTY-LICENSES.txt" "$PACKAGE/web/licenses/"
    rmdir "$PACKAGE/web/app/$RELEASE/licenses"
    cp -a "$ROOT/map-assets" "$PACKAGE/web/map"
    cp "$ROOT/backend/public/.htaccess" "$PACKAGE/web/.htaccess"
    # One line each in the webroot, pointing to the active release (@APP@ is replaced on installation).
    printf '<?php\nrequire %s;\n' "'@APP@/current/public/status.php'" > "$PACKAGE/web/api/status.php"
    printf '<?php\nrequire %s;\n' "'@APP@/current/public/gate.php'" > "$PACKAGE/web/gate.php"
    # Start page at the root of the webroot: assets from app/<release>/, the element finds the backend itself.
    sed -E "s#\./(favicon\.svg|fonts/|commonsight\.js)#app/$RELEASE/\1#g" "$ROOT/frontend/dist/index.html" > "$PACKAGE/web/index.html"
    # The release keeps its start page too, for a rollback (INSTALLATION.md).
    cp "$PACKAGE/web/index.html" "$PACKAGE/app/index.html"
    cp "$ROOT/install/config.example.php" "$ROOT/install/crontab.example" "$PACKAGE/"
    printf '%s\n%s\n' "$RELEASE" "$(head -n 1 "$ROOT/VERSION")" > "$PACKAGE/RELEASE"
    tar -C "$ROOT/build" -czf "$PACKAGE.tar.gz" "commonsight-$RELEASE"
    log "Package: build/commonsight-$RELEASE.tar.gz"
}

cmd_deploy() {
    prepare_account
    cmd_build

    log "Installing $RELEASE"
    cp -a "$PACKAGE/app" "$APP/releases/$RELEASE"
    # The backend first, then the web part (INSTALLATION.md, update): the new start page never meets the old backend.
    ln -sfn "releases/$RELEASE" "$APP/current.new" && mv -T "$APP/current.new" "$APP/current"
    compose exec -T php sh -c 'kill -USR2 1'
    rm -rf "$WEB/map" "$WEB/licenses"
    # status.php gets its path before it reaches the webroot: no request finds the placeholder.
    rm -rf "$PACKAGE/web-installed" && cp -a "$PACKAGE/web" "$PACKAGE/web-installed"
    sed -i "${PLACEHOLDERS[@]}" "$PACKAGE/web-installed/api/status.php" "$PACKAGE/web-installed/gate.php"
    cp -a "$PACKAGE/web-installed/." "$WEB/" && rm -rf "$PACKAGE/web-installed"
    # The operator's colours and logo stay; only files that are missing are added.
    cp -rn "$PACKAGE/custom" "$WEB/"
    (cd "$APP/releases" && ls -1 | sort | head -n -$KEEP_RELEASES | xargs -r rm -rf)
    (cd "$WEB/app" && ls -1 | sort | head -n -$KEEP_RELEASES | xargs -r rm -rf)

    as_account scheduler php "$FETCHER" check || fail "check reports problems"
    log "Done: http://localhost:$(port)/"
}

cmd_update_data() { as_account scheduler php -d memory_limit=256M "$FETCHER" run --all; }

cmd_update_tiles() { as_account scheduler "$HOME_IN/commonsight/current/bin/update-tiles.sh"; }

cmd_status() { as_account scheduler php "$FETCHER" status; }

cmd_test() {
    log "Backend"
    tool composer install --no-interaction --no-progress --quiet
    tool composer check-all
    log "Frontend"
    install_frontend_dependencies
    tool node npm run check
    tool node npm run format:check
    log "End-to-end"
    tool playwright npx playwright test
}

cmd_logs() { compose logs -f; }

case "${1:-}" in
    up) cmd_up "${2:-}" ;;
    down) cmd_down ;;
    build) cmd_build ;;
    deploy) cmd_deploy ;;
    update-data) cmd_update_data ;;
    update-tiles) cmd_update_tiles ;;
    status) cmd_status ;;
    test) cmd_test ;;
    logs) cmd_logs ;;
    *) sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac
