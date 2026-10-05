#!/usr/bin/env bash
# Monthly tile job (Architecture 8.1, V9): PMTiles archive for DACH plus the border zone from the latest
# Protomaps build.
#
#   update-tiles.sh                 create and activate the archive
#   update-tiles.sh --install FILE  install a ready-made file (emergency path, only steps 3 to 5)
#
# Procedure: 1. determine the build, 2. pmtiles extract with the border zone, 3. verify, 4. move to tiles/ and
# switch manifest.json via rename(), 5. delete previous archives 7 days after they were replaced (a marker
# .retired-<file> records when; the archive itself stays untouched, its ETag must not change while browsers read
# it). Retired archives are also removed before a build, so that at most the active one, the new one and one
# replaced within the last 7 days take space (about 3 × 8.5 GB at maxzoom 14). If a step fails, the old archive stays
# active and the partial file is deleted.

set -euo pipefail

readonly CS_APP="${CS_APP:-$HOME/commonsight}"
readonly CS_WEB="${CS_WEB:-$HOME/public_html}"
readonly WORK="${CS_APP}/tiles-work"
readonly TILES="${CS_WEB}/tiles"
readonly REGION="${CS_REGION:-${CS_WEB}/map/border-zone.geojson}"
readonly MAXZOOM="${MAXZOOM:-14}"
readonly MIN_BYTES="${MIN_BYTES:-1000000}"
readonly KEEP_DAYS=7
# Cron searches only /usr/bin:/bin; pmtiles is often installed to /usr/local/bin.
PATH="${PATH}:/usr/local/bin"
PMTILES="${PMTILES:-$(command -v pmtiles || echo "${CS_APP}/bin/pmtiles")}"
# The PHP CLI of the hoster (crontab.example passes it; /usr/bin/php may be missing or old).
PHP="${PHP:-php}"

log() { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"; }
fail() { log "ERROR: $*"; exit 1; }

latest_build() {
    curl -fsS --max-time 30 https://build-metadata.protomaps.dev/builds.json \
        | "$PHP" -r '$b = json_decode(stream_get_contents(STDIN), true); $k = array_column(is_array($b) ? $b : [], "key"); rsort($k); echo $k[0] ?? "";'
}

verify() {
    local file="$1"
    [[ -f "$file" ]] || fail "archive missing: $file"
    (( $(stat -c %s "$file") >= MIN_BYTES )) || fail "archive too small: $file"
    "$PMTILES" show "$file" >/dev/null || fail "pmtiles show cannot read $file"
    "$PMTILES" tile "$file" 5 17 11 >/dev/null || fail "sample tile 5/17/11 missing"
}

activate() {
    local file="$1" name previous
    # Date and time in the name: every build gets a new name, the archive is cached as immutable (.htaccess).
    name="dach-$(date -u +%Y%m%d-%H%M).pmtiles"
    mkdir -p "$TILES"
    previous="$(sed -n 's/.*"file":"\([^"]*\)".*/\1/p' "$TILES/manifest.json" 2>/dev/null || true)"
    mv -f "$file" "$TILES/$name"
    printf '{"file":"%s","maxzoom":%d,"createdAt":"%s"}\n' "$name" "$MAXZOOM" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$TILES/manifest.json.tmp"
    mv -f "$TILES/manifest.json.tmp" "$TILES/manifest.json"
    log "active: $name"
    if [[ -n "$previous" && "$previous" != "$name" && -f "$TILES/$previous" ]]; then
        : > "$TILES/.retired-$previous"
    fi
    mark_unmarked "$name"
    remove_retired
}

# Inactive archives without a marker (replaced before the markers existed) count as retired from now on.
mark_unmarked() {
    local active="$1" archive
    while IFS= read -r archive; do
        [[ -e "$TILES/.retired-${archive##*/}" ]] || : > "$TILES/.retired-${archive##*/}"
    done < <(find "$TILES" -maxdepth 1 -name 'dach-*.pmtiles' ! -name "$active")
}

# Deletes the archives replaced more than KEEP_DAYS ago: browsers that loaded the old manifest keep their archive.
remove_retired() {
    local marker archive
    while IFS= read -r marker; do
        archive="$TILES/${marker##*/.retired-}"
        log "remove: $archive"
        rm -f "$archive" "$marker"
    done < <(find "$TILES" -maxdepth 1 -name '.retired-dach-*.pmtiles' -mtime +"$KEEP_DAYS")
}

main() {
    mkdir -p "$WORK"
    [[ -x "$PMTILES" ]] || fail "pmtiles not found ($PMTILES)"
    if [[ "${1:-}" == "--install" ]]; then
        [[ $# -ge 2 ]] || fail "usage: update-tiles.sh --install FILE"
        verify "$2"
        activate "$2"
        return
    fi
    [[ -f "$REGION" ]] || fail "border zone outline missing: $REGION"
    # Space first: archives replaced over 7 days ago, and partial files of runs that died (e.g. killed by the hoster).
    [[ -d "$TILES" ]] && remove_retired
    find "$WORK" -maxdepth 1 -name 'dach-*.pmtiles' -delete
    local build
    build="$(latest_build)"
    [[ -n "$build" ]] || fail "no Protomaps build found"
    target="$WORK/dach-$(date -u +%Y%m%d-%H%M)-$$.pmtiles"
    trap 'rm -f "$target"' EXIT
    log "extract $build → $target (maxzoom $MAXZOOM)"
    "$PMTILES" extract "https://build.protomaps.com/$build" "$target" --region="$REGION" --maxzoom="$MAXZOOM"
    verify "$target"
    activate "$target"
}

main "$@"
