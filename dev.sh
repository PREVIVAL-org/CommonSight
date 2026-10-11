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
# Paths inside the containers.
readonly HOME_IN=/home/commonsight
readonly FETCHER="$HOME_IN/commonsight/current/bin/fetcher.php"

export CS_UID="${CS_UID:-$(id -u)}" CS_GID="${CS_GID:-$(id -g)}"

# Port of the web server: from .env (written by "up <port>"), otherwise 8090.
port() { sed -n 's/^CS_HTTP_PORT=//p' "$ROOT/.env" 2>/dev/null | grep . || echo 8090; }

log() { printf '→ %s\n' "$*"; }
fail() { printf '✗ %s\n' "$*" >&2; exit 1; }

compose() { docker compose --project-directory "$ROOT" -f "$ROOT/compose.yaml" "$@"; }
tool() { compose run --rm -T "$@"; }
as_account() { compose exec -T -u commonsight "$@"; }

cmd_up() {
    if [[ -n "${1:-}" ]]; then
        [[ "$1" =~ ^[0-9]+$ ]] || fail "Port must be a number: $1"
        printf 'CS_HTTP_PORT=%s\n' "$1" > "$ROOT/.env"
    fi
    "$ROOT/tools/dev/install.sh" account
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

# Installation package: app/ (to <APP>/releases/<release>/), web/ (to the webroot), templates for configuration and cron.
# The file steps are in tools/dev/package.sh, shared with dev.ps1.
cmd_build() {
    # The commit in the name only if the package is exactly that commit; with local changes it says so ("-dirty").
    local commit
    commit="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo nogit)"
    [[ -z "$(git -C "$ROOT" status --porcelain 2>/dev/null)" ]] || commit="$commit-dirty"
    RELEASE="$(date -u +%Y%m%d%H%M%S)-$commit"
    build_backend
    build_frontend
    [[ -d "$ROOT/map-assets/glyphs" ]] || { log "Downloading the glyphs of the map"; "$ROOT/tools/tiles/fetch-map-assets.sh"; }

    log "Assembling package $RELEASE"
    "$ROOT/tools/dev/package.sh" assemble "$RELEASE"
    tool -w "/src/build/commonsight-$RELEASE/app" composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
    "$ROOT/tools/dev/package.sh" seal "$RELEASE"
    log "Package: build/commonsight-$RELEASE.tar.gz"
}

cmd_deploy() {
    "$ROOT/tools/dev/install.sh" account
    cmd_build

    log "Installing $RELEASE"
    "$ROOT/tools/dev/install.sh" release "$RELEASE"
    compose exec -T php sh -c 'kill -USR2 1'
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
