#!/usr/bin/env bash
# Builds the installation package and installs it as a new release on the production server (doc/INSTALLATION.md,
# Update). Uses the shared SSH connection that must already be open.
#
#   ./deploy.sh                      PHP CLI on the server: /usr/bin/php
#   PHP=/usr/bin/php8.3 ./deploy.sh  another PHP CLI on the server
#
# Also installs the colours and logo of install/custom-previval and replaces the cron lines of the installation with
# those of install/crontab.example; other jobs stay.

set -euo pipefail

readonly ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# The server stays out of the repository: deploy.local (not committed, see .gitignore) sets
#   DEPLOY_HOST=<user>@<host>
#   DEPLOY_PORT=<ssh port>
#   DEPLOY_SOCKET=<control socket of the shared connection, e.g. ~/.ssh/cm-…>
# shellcheck source=/dev/null
[[ -f "$ROOT/deploy.local" ]] && source "$ROOT/deploy.local"
readonly HOST="${DEPLOY_HOST:?set DEPLOY_HOST in deploy.local}"
readonly PORT="${DEPLOY_PORT:-22}"
readonly SOCKET="${DEPLOY_SOCKET:?set DEPLOY_SOCKET in deploy.local}"
readonly KEEP_RELEASES=5
# Colours and logo of this installation, copied to <WEBROOT>/custom/ on every deploy.
readonly CUSTOM="$ROOT/install/custom-previval"
# PHP CLI on the server, for the checks and the cron lines.
readonly REMOTE_PHP="${PHP:-/usr/bin/php}"

log() { printf '→ %s\n' "$*"; }
fail() { printf '✗ %s\n' "$*" >&2; exit 1; }
remote() { ssh -S "$SOCKET" -p "$PORT" "$HOST" "$@"; }

ssh -S "$SOCKET" -O check -p "$PORT" "$HOST" 2>/dev/null || fail "No shared SSH connection at $SOCKET; open it first (see the head of this script)"

"$ROOT/dev.sh" build
PACKAGE="$(ls -1 "$ROOT"/build/commonsight-*.tar.gz | tail -n 1)"
RELEASE="$(basename "$PACKAGE" .tar.gz)"
RELEASE="${RELEASE#commonsight-}"
log "Deploying $RELEASE ($(head -n 1 "$ROOT/VERSION")) to $HOST"

# The package is unpacked in ~/.tmp and installed there, so nothing half-copied ever reaches the application directory.
remote "mkdir -p ~/.tmp && rm -rf ~/.tmp/commonsight-$RELEASE && tar -xzf - -C ~/.tmp" < "$PACKAGE"
remote "mkdir -p ~/.tmp/commonsight-$RELEASE/custom-site && tar -xzf - -C ~/.tmp/commonsight-$RELEASE/custom-site" < <(tar -C "$CUSTOM" -czf - .)

remote bash -s -- "$RELEASE" "$KEEP_RELEASES" "$REMOTE_PHP" <<'REMOTE'
set -euo pipefail
RELEASE="$1"
KEEP="$2"
PHP="$3"
APP="$HOME/lagezentrum"
# As paths.data in config.php (<WEBROOT>/data/v1).
WEB="$HOME/public_html/lagezentrum"
PKG="$HOME/.tmp/commonsight-$RELEASE"
CRON_BEGIN="# BEGIN CommonSight (deploy.sh, replaced on every deploy)"
CRON_END="# END CommonSight"

echo "→ Installing the release next to the running one"
cp -a "$PKG/app" "$APP/releases/$RELEASE"
# The new release against the existing config.php, while the old one still serves everything.
if ! "$PHP" "$APP/releases/$RELEASE/bin/fetcher.php" check; then
    rm -rf "$APP/releases/$RELEASE"
    echo "✗ Check of the new release failed; the running release is unchanged" >&2
    exit 1
fi

echo "→ Switching to $RELEASE"
# The backend first, then the web part: the new start page never meets the old backend.
ln -sfn "releases/$RELEASE" "$APP/current.new" && mv -T "$APP/current.new" "$APP/current"
sed -i "s#@APP@#$APP#g" "$PKG/web/api/status.php" "$PKG/web/gate.php"
rm -rf "$WEB/map" "$WEB/licenses"
cp -a "$PKG/web/." "$WEB/"
# Defaults for missing files, then the colours and logo of this installation from install/custom-previval.
cp -rn "$PKG/custom" "$WEB/"
cp -a "$PKG/custom-site/." "$WEB/custom/"

echo "→ Cron lines from crontab.example"
# Other jobs of the account stay; the block of the last deploy and any earlier line of this installation are replaced.
{
    { crontab -l 2>/dev/null || true; } | awk -v begin="$CRON_BEGIN" -v end="$CRON_END" -v app="$APP/current/bin/" '
        $0 == begin { skip = 1; next }
        $0 == end { skip = 0; next }
        skip || index($0, app) { next }
        { print }'
    echo "$CRON_BEGIN"
    grep -v '^#' "$PKG/crontab.example" | grep . | sed -e "s#@PHP@#$PHP#g" -e "s#@APP@#$APP#g" -e "s#@WEBROOT@#$WEB#g"
    echo "$CRON_END"
} | crontab -

echo "→ Keeping the last $KEEP releases"
(cd "$APP/releases" && ls -1 | sort | head -n -"$KEEP" | xargs -r rm -rf)
(cd "$WEB/app" && ls -1 | sort | head -n -"$KEEP" | xargs -r rm -rf)
rm -rf "$PKG"

"$PHP" "$APP/current/bin/fetcher.php" check
REMOTE

log "Done: $RELEASE is live"
