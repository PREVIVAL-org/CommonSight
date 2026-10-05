#!/usr/bin/env bash
# Downloads the glyphs (Noto Sans) of the Protomaps basemap to map-assets/glyphs/ (Architecture 8.2). Self-hosted, so
# that no third parties are contacted while the map is built (R-05). License: LICENSES/OFL-1.1.txt.
#
#   tools/tiles/fetch-map-assets.sh

set -euo pipefail

readonly ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly TARGET="$ROOT/map-assets"
readonly ARCHIVE="https://codeload.github.com/protomaps/basemaps-assets/tar.gz/refs/heads/main"
readonly FONTS=("Noto Sans Regular" "Noto Sans Medium" "Noto Sans Italic")

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

curl -fsSL --max-time 300 "$ARCHIVE" -o "$work/assets.tar.gz"
members=()
for font in "${FONTS[@]}"; do
    members+=("basemaps-assets-main/fonts/$font")
done
tar -xzf "$work/assets.tar.gz" -C "$work" "${members[@]}"

mkdir -p "$TARGET/glyphs"
for font in "${FONTS[@]}"; do
    rm -rf "$TARGET/glyphs/$font"
    mv "$work/basemaps-assets-main/fonts/$font" "$TARGET/glyphs/$font"
done
printf 'Glyphs: %d files\n' "$(find "$TARGET/glyphs" -name '*.pbf' | wc -l)"
