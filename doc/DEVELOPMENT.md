# Development and local environment

The local environment is set up like an instance in operation: same web server configuration, PHP settings, schedule and tile depth. Everything runs in Docker; the machine only needs Docker, Git and a shell to start the commands: `dev.sh` (Bash) on Linux, `dev.ps1` (PowerShell) on Windows. Both have the same commands and produce the same environment in `run/`.

## Prerequisites

For every system:

- **Disk space:** about 15 GB free, most of it for the tile archive (about 9 GB) and the images.
- **Port 8090** free on the machine, or another one chosen at the first start.
- **Internet access** to GitHub, Docker Hub, `mcr.microsoft.com` (Playwright), npm, Packagist and the Protomaps builds (tiles); a proxy must let these through.

### Linux

| Tool | Check | Notes |
|---|---|---|
| Docker Engine with Compose v2 | `docker compose version` | the user in the group `docker`, so that `docker` runs without `sudo` |
| Git | `git --version` | |
| Bash with GNU coreutils | `bash --version` | part of every common distribution |

### Windows

| Tool | Install | Check |
|---|---|---|
| Windows 10 22H2 or Windows 11, 64 bit, virtualization switched on in the firmware | | Task Manager, Performance, CPU: "Virtualization: Enabled" |
| WSL 2 | `wsl --install` (administrator), then restart | `wsl --status` |
| Docker Desktop with the WSL 2 backend (default) | `winget install Docker.DockerDesktop`, then start it once and accept the terms | `docker compose version` |
| Git for Windows | `winget install Git.Git` | `git --version` |
| PowerShell 7 | `winget install Microsoft.PowerShell`, then start it as `pwsh` (Start menu: "PowerShell 7") | `$PSVersionTable.PSVersion` shows 7.x |

Docker Desktop must be running whenever `dev.ps1` is used (whale icon in the taskbar). Open a new PowerShell 7 window after the installations, so that `docker` and `git` are found. The built-in Windows PowerShell 5.1 runs `dev.ps1` too, but writes files with `>` and `>>` as UTF-16, which Git and the containers cannot read; use PowerShell 7 for all commands in this document.

## First start on Linux

1. **Clone the repo**

   ```sh
   git clone git@github.com:PREVIVAL-org/CommonSight.git       # ssh use
   git clone https://github.com/PREVIVAL-org/CommonSight.git   # https use
   cd CommonSight
   ```

   Clones the main branch of CommonSight to a local folder on the user's machine.

2. **Start the environment**

   ```sh
   ./dev.sh up            # or ./dev.sh up 8100 for another port
   ```

   Builds the images from `docker/` (Apache, PHP-FPM, scheduler with cron and pmtiles), creates `run/` with the directories of the account and `config.php`, and starts the three containers. The first run takes a few minutes. The web server answers, but there is no release yet.

3. **Deliver the code**

   ```sh
   ./dev.sh deploy
   ```

   Installs the dependencies, generates the master data, builds the frontend, downloads the glyphs of the map, assembles the package and installs it as the first release. Ends with the environment check (`check`). From now on the scheduler fetches the sources on its schedule.

4. **Fetch the data**

   ```sh
   ./dev.sh update-data
   ```

   Fetches every layer at once, about two minutes. Without this step the schedule fills the layers within about 15 minutes.

5. **Build the tile archive**

   ```sh
   ./dev.sh update-tiles
   ```

   Downloads the basemap for DACH plus the border zone up to zoom 14 (about 9 GB, 2 to 15 minutes). Until then the map has no basemap; lists, key figures and points work already.

6. Open <http://localhost:8090/> (or the chosen port). The site listens on this machine only; `CS_HTTP_BIND=0.0.0.0` in `.env` opens it to the network, e.g. to test on a phone.

Steps 3 and 4 are needed only once: data and tiles stay in `run/`, also across `down`, `up` and later deploys.

## First start on Windows

In PowerShell 7 (`pwsh`, not as administrator), with Docker Desktop running:

1. **Clone the repo**

   ```powershell
   git clone https://github.com/PREVIVAL-org/CommonSight.git
   cd CommonSight
   ```

   The repository sets LF line endings for every file (`.gitattributes`), whatever Git for Windows is set to: the shell scripts, the crontab and `config.php` run in Linux containers, where a CR breaks them. A clone made before `.gitattributes` existed is fixed with a fresh clone (or `git add --renormalize .`).

2. **Allow local scripts** (once per Windows user)

   ```powershell
   Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
   ```

   Usually already set in PowerShell 7; the command does no harm then. Without changing the policy, each call can be started as `pwsh -ExecutionPolicy Bypass -File .\dev.ps1 <command>`.

3. **Start the environment**

   ```powershell
   .\dev.ps1 up          # or .\dev.ps1 up 8100 for another port
   ```

   As on Linux, plus the tool container `shell` (Bash, coreutils, curl, tar), in which `dev.ps1` runs the file steps of `tools/dev/`: the same scripts `dev.sh` runs on the machine.

4. **Deliver the code, fetch the data, build the tile archive**

   ```powershell
   .\dev.ps1 deploy
   .\dev.ps1 update-data
   .\dev.ps1 update-tiles
   ```

   The same steps as 3 to 5 on Linux.

5. Open <http://localhost:8090/> (or the chosen port).

The checkout on the Windows drive works, but Docker reaches it through a slow file bridge: `npm ci`, the build and the tests take noticeably longer than on Linux. Faster: clone into the Linux file system of WSL (in the WSL shell, e.g. `~/CommonSight`, reachable in Explorer as `\\wsl$\Ubuntu\home\<user>\CommonSight`) and follow the first start on Linux there; Docker Desktop provides `docker` in WSL (Settings, Resources, WSL integration).

## Local AI tool settings

AI coding agents keeps its local settings in the folder `.claude/`, Codex in `.codex/`. They must not get into the repository. Ignore both folders once per machine in Git's global ignore file, for all repositories (on Windows in PowerShell 7 or Git Bash):

```sh
git config --global core.excludesFile ~/.gitignore_global
echo ".claude/" >> ~/.gitignore_global
echo ".codex/" >> ~/.gitignore_global
```

## Afterwards

On Windows `.\dev.ps1` with the same command instead of `./dev.sh`.

| Situation | Command |
|---|---|
| code changed | `./dev.sh deploy` |
| `docker/`, `install/` or `compose.yaml` changed | `./dev.sh up` (rebuilds the images) |
| check everything before a commit | `./dev.sh test` |
| stop the environment | `./dev.sh down`; `./dev.sh up` starts it again with the same data |
| start from scratch | `./dev.sh down`, delete `run/`, then the first start again |

## Commands

| Command | Does |
|---|---|
| `./dev.sh up [port]` | builds the images and starts web server, PHP and scheduler; optional port (default 8090), remembered in `.env` |
| `./dev.sh down` | stops the environment; data and tiles stay in `run/` |
| `./dev.sh build` | builds the installation package `build/commonsight-<release>.tar.gz` (doc/INSTALLATION.md) |
| `./dev.sh deploy` | builds the package and installs it as a new release: switches `current`, reloads PHP, checks the environment |
| `./dev.sh update-data` | fetches every layer in every scope at once, regardless of its interval |
| `./dev.sh update-tiles` | builds and activates a new tile archive from the latest Protomaps build |
| `./dev.sh status` | state of all layers (status, version, last check, items, errors) |
| `./dev.sh test` | all checks: backend (code style, PHPStan, deptrac, PHPUnit), frontend (types, lint, dependencies, Vitest, build, formatting), end-to-end (Playwright) |
| `./dev.sh logs` | follows the logs of the services |

## Services

| Service | Image | Task |
|---|---|---|
| `web` | Apache 2.4 (`docker/web`) | delivers the webroot, `.htaccess` active, PHP via FastCGI |
| `php` | PHP-FPM 8.4 (`docker/php`, target `fpm`) | status endpoint, runs as the account user |
| `scheduler` | PHP CLI 8.4 with cron and pmtiles (`docker/php`, target `scheduler`) | fetcher lanes and housekeeping (`install/crontab.example` without the monthly tile job: tiles only by `./dev.sh update-tiles`) |
| `composer`, `node`, `playwright` | official images | build and tests, only on demand |

## Layout

`run/` is the home of the hosting account and is mounted into the containers as `/home/commonsight`. It is not versioned.

```
run/
├─ commonsight/              application, outside the webroot
│  ├─ releases/<release>/    a release: src, bin, public, generated, vendor, plugins (backend, data), index.html
│  ├─ current -> releases/…  active release
│  ├─ config.php             configuration (from install/config.example.php, never overwritten)
│  ├─ state/ cache/ locks/   status files, detail caches, locks
│  ├─ logs/                  fetcher.log, cron.log, tiles.log, client-YYYY-MM-DD.log
│  └─ tiles-work/            working directory of the tile job
└─ public_html/              webroot, root of the (sub)domain
   ├─ index.html             start page
   ├─ .htaccess
   ├─ api/status.php         status endpoint (one line, requires the code of the active release)
   ├─ api/report.php         error reports of the browsers (one line, like status.php)
   ├─ api/situation.php      situation level per country for other sites (one line, like status.php)
   ├─ box/situation.js       box script for a forum, fixed address
   ├─ data/v1/               snapshots
   ├─ tiles/                 tile archive and manifest.json
   ├─ map/                   glyphs, regions, mask, border zone
   ├─ licenses/              all license texts
   └─ app/<release>/         frontend bundle
```

`deploy` keeps the last five releases of backend and frontend. `build/` holds the package of the last build.

## Configuration

`run/commonsight/config.php` holds paths, HTTP limits, lanes, the settings per layer (e.g. the radiation thresholds) and per source (switch, lane, interval, rank, secrets). It is created once from `install/config.example.php`, the same template as for an installation; later changes there do not reach an existing environment.

## Adding a source

The full guide with code samples is [PLUGIN-DEVELOPMENT.md](PLUGIN-DEVELOPMENT.md); the steps in short:

A source is a plugin package in `plugins/providers/<id>/` (a news feed in `plugins/news/<id>/`); nothing outside it changes (concept in `internal/SOURCE-INTEGRATION.md`, rules in `doc/ARCHITECTURE.md` 4.1 to 4.3). The existing plugins are the templates, e.g. `plugins/providers/ehyd` (one JSON request), `plugins/providers/chmi` (stations and one request per station), `plugins/providers/geosphere-warnings` (details per message).

1. `plugin.php` returns the factory; the factory describes the source (`SourceDescription`: id = folder name, name, attribution verbatim as the provider requires, layer, scopes, schedule with update rate, terms minimum and lane, expectations, HTTP budget, secrets, rank) and creates the plugin from its environment.
2. `backend/` with namespace `CommonSight\Plugin\<Name>`: request, parser, record types, mapper straight to an item kind of the model; the SDK (`backend/src/Sdk/`) has the common parts.
3. `messages/de.json` for texts of its own (keys `source.<id>.…`), `catalog.json` for new quantities or categories, `data/` for its master data.
4. `PROFILE.md`, a recording per scope (`docker compose run --rm composer php ../tools/record/record-plugin.php <id>`, secrets as `CS_SECRET_<NAME>`), tests of its own logic in `tests/`, edge cases in `tests/cases/`.
5. `docker compose run --rm composer composer check-all`: the contract build finds the plugin, and the generic plugin test checks description, profile, recordings, items and texts without any entry elsewhere. Then `./dev.sh deploy` and `fetcher.php sources` in the `scheduler` container.

## Adding a layer

A layer is a plugin package in `plugins/layers/<id>/`; nothing outside it changes (concept in `internal/LAYER-PLUGINS.md`, rules in `doc/ARCHITECTURE.md` 4.1, 4.2 and 9.1). Templates: `plugins/layers/pollen` with `plugins/providers/open-meteo-pollen` (the smallest complete layer with its source, added this way), `plugins/layers/air` (model values, a tile), `plugins/layers/radiation` (own assessment, settings, key figures, legend, tile), `plugins/layers/warnings` (map notice and short notice).

1. `plugin.php` returns the factory; the factory describes the layer (`LayerDescription`: id = folder name, scope country or global, border zone, color, lucide icon, on the map, region filter, list view, default active, order, settings) and creates the layer from its environment; the layer returns a `LayerDefinition` per scope (pipeline steps from `Sdk/Layer`, note, key figures, name and link).
2. `data/names.json` with name and link per scope, `messages/de.json` with `layer.<id>.name` and its notes (`layer.<id>.note` or `.note.<scope>`), `LAYER.md`, `schema/stats.schema.json` if it has key figures.
3. `frontend/map.ts` with `map` (renderer and draw rank) if it is on the map, `frontend/ui.ts` with `ui` for legend, tile, panel or notice; only `@sdk/…`, `@contract/…` and its own modules.
4. The sources of the layer as source plugins (`layer` = the new id), each with its recording.
5. Tests in the package: `tests/<Name>LayerTest.php` records its fixtures, `frontend/*.test.ts` tests its parts. `composer build-contract`, `composer check-all` and `npm run check` find the layer and check it generically.

## Version

`VERSION` in the repository root holds the version of CommonSight, maintained by hand (e.g. `0.1.21 (7727e0987a)`, version and commit). It is used in two places:

- **Frontend:** the build reads it and shows it below the content ("Version …", U-04). After a change, `./dev.sh deploy` is needed for it to appear.
- **Package:** `./dev.sh build` writes it into the file `RELEASE` of the package, next to the release name.

The release name (`<UTC timestamp>-<commit>`, e.g. `20261003095147-cd8c0fd`; `-dirty` at the end when the working tree had local changes) is independent of it: it is created on every build and names the directories `releases/<release>/` and `app/<release>/`.

## Notes

- The containers run with the user and group of the machine (`CS_UID`, `CS_GID`), so that the files in `run/` belong to you. On Windows `dev.ps1` uses 1000; Docker Desktop gives the files to the Windows user anyway.
