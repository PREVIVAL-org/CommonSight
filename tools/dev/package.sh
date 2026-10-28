#!/usr/bin/env bash
# File steps of the installation package (doc/DEVELOPMENT.md), shared by dev.sh and dev.ps1; dev.sh runs it on the
# machine, dev.ps1 in the tool container "shell". Backend and frontend must be built before (composer, npm).
#
#   tools/dev/package.sh assemble <release>   copy code, bundle, map assets and entry points to build/commonsight-<release>/
#   tools/dev/package.sh seal <release>       after "composer install --no-dev" in its app/: remove build files, pack the archive

set -euo pipefail

readonly ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly RELEASE="${2:?release missing}"
readonly PACKAGE="$ROOT/build/commonsight-$RELEASE"

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

assemble() {
    rm -rf "$ROOT"/build/commonsight-* && mkdir -p "$PACKAGE/app/bin" "$PACKAGE/web/api" "$PACKAGE/web/app" "$PACKAGE/web/licenses"
    cp -a "$ROOT"/backend/{src,bin,public,generated,composer.json,composer.lock} "$PACKAGE/app/"
    copy_plugins "$PACKAGE/app/plugins"
    # In the release the plugins lie in app/plugins/ instead of ../plugins/ next to backend/.
    sed -i 's#"\.\./plugins/#"plugins/#g' "$PACKAGE/app/composer.json"
    cp "$ROOT/tools/tiles/update-tiles.sh" "$PACKAGE/app/bin/"
    cp -a "$ROOT/frontend/dist" "$PACKAGE/web/app/$RELEASE"
    # Colours and logo of the operator (ACCESS-AND-BRANDING B-D3): defaults beside web/, copied without overwriting.
    mv "$PACKAGE/web/app/$RELEASE/custom" "$PACKAGE/custom"
    # The box script for other sites (situation level) under a fixed address: a forum links it once.
    mv "$PACKAGE/web/app/$RELEASE/box" "$PACKAGE/web/box"
    # All license texts in one place: own code, third-party parts, libraries of the bundle.
    cp "$ROOT/LICENSE" "$ROOT"/LICENSES/* "$PACKAGE/web/licenses/"
    mv "$PACKAGE/web/app/$RELEASE/licenses/THIRD-PARTY-LICENSES.txt" "$PACKAGE/web/licenses/"
    rmdir "$PACKAGE/web/app/$RELEASE/licenses"
    cp -a "$ROOT/map-assets" "$PACKAGE/web/map"
    cp "$ROOT/backend/public/.htaccess" "$PACKAGE/web/.htaccess"
    # One line each in the webroot, pointing to the active release (@APP@ is replaced on installation).
    printf '<?php\nrequire %s;\n' "'@APP@/current/public/status.php'" > "$PACKAGE/web/api/status.php"
    printf '<?php\nrequire %s;\n' "'@APP@/current/public/gate.php'" > "$PACKAGE/web/gate.php"
    printf '<?php\nrequire %s;\n' "'@APP@/current/public/report.php'" > "$PACKAGE/web/api/report.php"
    printf '<?php\nrequire %s;\n' "'@APP@/current/public/situation.php'" > "$PACKAGE/web/api/situation.php"
    # Start page at the root of the webroot: assets from app/<release>/, the element finds the backend itself.
    sed -E "s#\./(favicon\.svg|fonts/|commonsight\.js)#app/$RELEASE/\1#g" "$ROOT/frontend/dist/index.html" > "$PACKAGE/web/index.html"
    # The release keeps its start page too, for a rollback (INSTALLATION.md).
    cp "$PACKAGE/web/index.html" "$PACKAGE/app/index.html"
    cp "$ROOT/install/config.example.php" "$ROOT/install/crontab.example" "$PACKAGE/"
    printf '%s\n%s\n' "$RELEASE" "$(head -n 1 "$ROOT/VERSION")" > "$PACKAGE/RELEASE"
}

seal() {
    # build-contract.php needs contract/ and the plugin sources; it runs only at build time.
    rm -f "$PACKAGE/app/composer.json" "$PACKAGE/app/composer.lock" "$PACKAGE/app/bin/build-contract.php"
    tar -C "$ROOT/build" -czf "$PACKAGE.tar.gz" "commonsight-$RELEASE"
}

case "${1:-}" in
    assemble) assemble ;;
    seal) seal ;;
    *) sed -n '2,7p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac
