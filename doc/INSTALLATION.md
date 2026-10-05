# Installation on a shared host

Manual installation of a release on a hosting account with Apache and PHP; paths and names are free to choose.

## Requirements

- PHP 8.3 or newer for the web server and on the command line, with curl, json, mbstring, xmlreader, dom and zlib; brotli optional (smaller snapshots).
- Apache with `.htaccess` (`AllowOverride All`), mod_rewrite and mod_headers; byte ranges for static files (standard).
- SSH access to the account, cron jobs (every minute), symlinks.
- About 30 GB of disk space, almost all for the tile archives: during the monthly build the active archive, the new one and one replaced within the last 7 days exist at the same time (about 8.5 GB each).
- `open_basedir` (if set) must include the application directory and the webroot.

## Directories

| Placeholder | Meaning | Example |
|---|---|---|
| `<APP>` | application directory, outside the webroot | `/home/<account>/commonsight` |
| `<WEBROOT>` | webroot of the (sub)domain | `/home/<account>/public_html/commonsight` |
| `<PHP>` | PHP command line | `/usr/bin/php` |
| `<URL>` | public address of `<WEBROOT>` | `https://<domain>/commonsight` |

```
<APP>/
├─ releases/<release>/   code of a release
├─ current -> releases/… active release
├─ config.php            configuration
├─ state/ cache/ locks/ logs/ tiles-work/
└─ bin/pmtiles           program for the tile job
<WEBROOT>/
├─ index.html, gate.php, .htaccess
├─ custom/               colours and logo of this installation (never overwritten)
├─ api/status.php
├─ data/v1/              snapshots (written by the fetcher)
├─ tiles/                tile archive
├─ map/                  glyphs, regions, mask, border zone
├─ licenses/             all license texts
└─ app/<release>/        frontend bundle
```

Webroot and home may lie in different places (on the example host `public_html` is a link to `/usr/www/users/<userid>`). The files in the webroot therefore point to `<APP>` with an absolute path.

## Package

On a development machine with the repository:

```sh
./dev.sh build
```

The result is `build/commonsight-<release>.tar.gz` with:

| Part | Goes to |
|---|---|
| `app/` | `<APP>/releases/<release>/` |
| `web/` (including `licenses/` with all license texts) | `<WEBROOT>/` |
| `custom/` | defaults for `<WEBROOT>/custom/`, copied without overwriting |
| `config.example.php` | template for `<APP>/config.php` |
| `crontab.example` | template for the cron jobs |
| `RELEASE` | release name and version |

## First installation

1. Upload the package and unpack it in a temporary directory, e.g. `~/tmp`:
   ```sh
   tar -xzf commonsight-<release>.tar.gz
   cd commonsight-<release>
   ```
2. Create the directories:
   ```sh
   mkdir -p <APP>/{releases,state,cache,locks,logs,tiles-work,bin} <WEBROOT>/{data/v1,tiles}
   ```
3. Code and webroot:
   ```sh
   cp -a app <APP>/releases/<release>
   ln -sfn releases/<release> <APP>/current
   sed -i 's#@APP@#<APP>#g' web/api/status.php web/gate.php
   cp -a web/. <WEBROOT>/
   cp -rn custom <WEBROOT>/
   ```
4. Configuration: copy `config.example.php` to `<APP>/config.php` and replace `@APP@` and `@WEBROOT@` with the absolute paths (every key: `CONFIGURATION.md`), and enter your contact address under `http.contact` (sent to the providers in the User-Agent). The comments in the file explain the other keys; a misspelled key is refused. It can hold secrets of sources (API keys): make it readable for the account only (`chmod 600 <APP>/config.php`).
5. Tile program: download `pmtiles` for Linux (<https://github.com/protomaps/go-pmtiles/releases>) to `<APP>/bin/pmtiles` and make it executable (`chmod +x`).
6. Cron jobs: replace `@PHP@`, `@APP@` and `@WEBROOT@` in `crontab.example` and enter the five lines with `crontab -e` (or in the cron job manager of the hoster): one per lane (`fast` every minute, `heavy` every 5 minutes, `slow` every 30 minutes), housekeeping and the tile job.
7. Check and fill:
   ```sh
   <PHP> <APP>/current/bin/fetcher.php check
   <PHP> -d memory_limit=256M <APP>/current/bin/fetcher.php run --all
   CS_APP=<APP> CS_WEB=<WEBROOT> <APP>/current/bin/update-tiles.sh
   ```
   `check` lists PHP version, extensions, writable directories, the sources per lane and whether their typical run times fit the budget of the lane, and the settings of every layer with their defaults; an invalid setting or a layer that cannot be defined in a scope fails the check. `run --all` fetches every source once. The tile job downloads about 9 GB and needs 2 to 15 minutes.

## Update

1. Build and unpack the new package as above.
2. Install it next to the old release, check it, then put it live:
   ```sh
   cp -a app <APP>/releases/<release>
   <PHP> <APP>/releases/<release>/bin/fetcher.php check
   ```
   The check runs the new release against the existing `config.php` while the old one still serves everything. Only when it passes (adjust `config.php` first, see step 3), switch and then copy the web part: the new backend first, so that the new start page never meets the old one. The path in `status.php` is set in the package before copying, so no request meets the placeholder; map assets and licenses are replaced as a whole:
   ```sh
   ln -sfn releases/<release> <APP>/current.new && mv -T <APP>/current.new <APP>/current
   sed -i 's#@APP@#<APP>#g' web/api/status.php web/gate.php
   rm -rf <WEBROOT>/map <WEBROOT>/licenses
   cp -a web/. <WEBROOT>/
   cp -rn custom <WEBROOT>/
   ```
   `cp -rn` keeps the colours and logo in `custom/` and only adds files a new release brings.
3. Compare `config.example.php` with `<APP>/config.php`; new keys are added by hand. An update never changes `config.php`, data or tiles. Settings of former releases are refused with a hint to their replacement, so the fetcher does not start with them:
   - From the release with sources as plugins on: `layers.<id>.intervalSec` is replaced by `sources.<id>.intervalSec`, the section `budgets` by `lanes`, and the cron line of the lane `slow` is added. After the update every source is fetched again in its lane, because outcomes are kept per release.
   - From the release with the auth providers on: the section `memberGate` is replaced by `auth` (see Access control below); the start page then goes through `gate.php`, which the update brings.
   - From the release with layers as plugins on: the section `assessment` (`radiationWarningUSvH`, `radiationHighUSvH`) is replaced by `'layers' => ['radiation' => ['settings' => ['warningUSvH' => …, 'highUSvH' => …]]]`; the section `layers` holds only `settings` per layer, and a layer that does not exist is an error. `fetcher.php check` lists the settings of every layer with their defaults.
4. Remove old releases in `<APP>/releases/` and `<WEBROOT>/app/` when they are no longer needed (keep a few for a rollback).

PHP may keep the old target of `current` for up to two minutes (realpath cache, OPcache); requests in this time still use the previous release.

Outcomes of sources are kept per release: after every update and every rollback each source is fetched once more at its next lane run, regardless of its interval (never sooner than the terms of its provider allow).

## Rollback

Switch `current` back to the previous release and restore its start page, which every release keeps (it names the frontend bundle in `app/<release>/`, which is still in the webroot):

```sh
ln -sfn releases/<previous release> <APP>/current.new && mv -T <APP>/current.new <APP>/current
cp <APP>/releases/<previous release>/index.html <WEBROOT>/index.html
```

Releases built before this note have no `index.html` of their own; for them the start page comes from their unpacked package (`commonsight-<previous release>/web/index.html`).

`.htaccess`, `map/` and `licenses/` stay at the newer release. That is fine as long as the newer release did not change them in a way the older one cannot use; otherwise copy them from the unpacked package of the previous release as in the update.

## Checks

```sh
<PHP> <APP>/current/bin/fetcher.php status                 # state of all layers
<PHP> <APP>/current/bin/fetcher.php sources                # every source: lane, interval, last success, failures, backoff, typical run time
curl -s '<URL>/api/status.php?scope=DE' | head -c 200
curl -sI -r 0-99 '<URL>/tiles/<archive>.pmtiles'           # 206, Content-Range, no Content-Encoding
tail <APP>/logs/cron.log <APP>/logs/fetcher.log <APP>/logs/tiles.log
```

## Tiles without enough time or space on the server

Build the archive elsewhere (e.g. `./dev.sh update-tiles` locally, the file is in `run/public_html/tiles/`), upload it and activate it on the server:

```sh
CS_APP=<APP> CS_WEB=<WEBROOT> <APP>/current/bin/update-tiles.sh --install <file>.pmtiles
```

## Access control

By default the start page is open to everyone. It can be closed to everyone but the members of a community through an auth provider (`plugins/auth/`); the first is WoltLab Suite. Only the start page is protected: data, tiles and `api/status.php` stay public, their content comes from public sources. A visitor who is not admitted sees the header and a card with the way to log in or register at the community. On any failure (provider not reachable, wrong settings) the page stays closed and the reason goes to `logs/fetcher.log` (`auth.failed`, `gate.exception`); nothing about visitors is kept or logged.

For a WoltLab forum, in `config.php`:

```php
'auth' => [
    'provider' => 'woltlab',
    'settings' => [
        'cookie' => 'wsc_1d3758_user_session',    // name of the forum's session cookie (browser, developer tools)
        'community' => 'PREVIVAL.org',             // shown on the members card
        'forumUrl' => 'https://previval.org/',     // for login (back to CommonSight) and registration
        'database' => ['host' => 'localhost', 'port' => 3306, 'name' => 'forum', 'user' => 'cs_read', 'password' => '…'],
        // or instead of database: 'forumConfig' => '/path/of/the/forum/config.inc.php'
    ],
],
```

- A member is a logged-in user of the forum whose session was active within the last 60 days and who is not banned; logging out takes effect at once. The forum and CommonSight must share the cookie, i.e. run on the same domain or its subdomains with the forum's cookie domain set accordingly.
- `database`: a user of its own that may only read, best: `GRANT SELECT ON forum.wcf1_user_session TO 'cs_read'@'localhost'; GRANT SELECT ON forum.wcf1_user TO 'cs_read'@'localhost';` (`instance` for a prefix other than `wcf1_`). PHP needs `pdo_mysql`.
- `forumConfig`: reads the access data of the forum from its `config.inc.php` (no setup, but with the forum's full rights).
- `fetcher.php check` names the provider and refuses wrong settings.

## Colours and logo

`<WEBROOT>/custom/` holds the colours and the logo of the installation; an update never overwrites it:

- `theme.css`: the colours as CSS variables of the element, for light and dark (`--cs-header-bg`, `--cs-header-text`, `--cs-header-line`, `--cs-accent`, `--cs-link`, …; all of them in `frontend/src/theme/tokens.css`). Keep text readable: at least 4.5:1 against its background. Warning and assessment colours are fixed.
- `logo.svg`: the logo in the header, 32 px high, the width follows (wide logos are fine).
- `favicon.svg`: the icon in the browser tab.
- `branding.json` (optional): the title of the browser tab and the name in the header next to the logo (both "CommonSight" without them), logo and favicon in another format, a logo for the dark theme, where the logo leads and its text for screen readers; paths relative to the webroot or http(s) addresses:
  ```json
  { "title": "Example e.V. · Map", "name": "Example Map", "logo": "./custom/my-logo.png", "logoDark": "./custom/my-logo-dark.png", "logoLink": "https://example.org/", "logoAlt": "Example e.V.", "favicon": "./custom/my-favicon.png" }
  ```
  `gate.php` sets them on the start page; a broken file keeps the defaults and is reported in `logs/fetcher.log` (`branding.invalid`).

The look of PREVIVAL (lagezentrum.previval.org) is ready in the package source: `install/custom-previval/` (title "PREVIVAL Lagezentrum", name "LAGEZENTRUM", logo, favicon, header colours of the forum); copy its files to `<WEBROOT>/custom/`. `deploy.sh` does that on every deploy to that server.

The footer always shows "Powered by CommonSight" with the version.
