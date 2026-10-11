#!/usr/bin/env bash
# File steps of the local environment in run/ (doc/DEVELOPMENT.md), shared by dev.sh and dev.ps1; dev.sh runs it on
# the machine, dev.ps1 in the tool container "shell".
#
#   tools/dev/install.sh account              directories of the account, config.php from the template (once)
#   tools/dev/install.sh release <release>    install build/commonsight-<release>/ as the active release

set -euo pipefail

readonly ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly APP="$ROOT/run/commonsight"
readonly WEB="$ROOT/run/public_html"
readonly KEEP_RELEASES=5
# Placeholders of the templates in install/ for the local environment: paths inside the containers.
readonly HOME_IN=/home/commonsight
readonly PLACEHOLDERS=(-e "s#@PHP@#/usr/bin/php#g" -e "s#@APP@#$HOME_IN/commonsight#g" -e "s#@WEBROOT@#$HOME_IN/public_html#g")

account() {
    mkdir -p "$APP"/{releases,state,cache,locks,logs,tiles-work} "$WEB"/{api,data/v1,tiles,map,app}
    [[ -f "$APP/config.php" ]] || { sed "${PLACEHOLDERS[@]}" "$ROOT/install/config.example.php" > "$APP/config.php"; echo "→ config.php created"; }
}

release() {
    local release="${1:?release missing}"
    local package="$ROOT/build/commonsight-$release"
    cp -a "$package/app" "$APP/releases/$release"
    # The backend first, then the web part (INSTALLATION.md, update): the new start page never meets the old backend.
    ln -sfn "releases/$release" "$APP/current.new" && mv -T "$APP/current.new" "$APP/current"
    rm -rf "$WEB/map" "$WEB/licenses"
    # status.php gets its path before it reaches the webroot: no request finds the placeholder.
    rm -rf "$package/web-installed" && cp -a "$package/web" "$package/web-installed"
    sed -i "${PLACEHOLDERS[@]}" "$package/web-installed/api/status.php" "$package/web-installed/api/report.php" "$package/web-installed/gate.php"
    cp -a "$package/web-installed/." "$WEB/" && rm -rf "$package/web-installed"
    # The operator's colours and logo stay; only files that are missing are added.
    cp -rn "$package/custom" "$WEB/"
    (cd "$APP/releases" && ls -1 | sort | head -n -$KEEP_RELEASES | xargs -r rm -rf)
    (cd "$WEB/app" && ls -1 | sort | head -n -$KEEP_RELEASES | xargs -r rm -rf)
}

case "${1:-}" in
    account) account ;;
    release) release "${2:-}" ;;
    *) sed -n '2,7p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac
