# Blazing Sun — blazingsun.space

Portfolio site of **Milorad Đuković**, Senior Full Stack Web Engineer.

| Part | Technology |
|---|---|
| Frontend | One page, **Vite**, **SCSS**, vanilla JavaScript as **ES6 classes**. Two themes (White Moon / Blazing Sun), three languages (en / sr / fr), offline PWA |
| Backend | **PHP 8.5** JSON API (no framework, no runtime Composer dependencies) with **MySQL**; every database operation is a **stored procedure** |
| Local dev | **Docker Compose**: nginx 1.31, PHP-FPM 8.5, MySQL 26.10 |
| CI/CD | **GitHub Actions**: lint → unit → PHP API → build → E2E on every PR and push; FTPS deploy to Hostinger only for release tags (`vX.Y.Z`) |

The first API feature is a **page-visit counter**. Every page load is counted, and the all-time total is written into a **hidden** element in the footer, so you can only see it in DevTools (see [Checking the visit counter](#checking-the-visit-counter)).

---

## Table of contents

1. [Requirements](#requirements)
2. [Quick start](#quick-start)
3. [Local Docker stack](#local-docker-stack)
4. [npm and Composer scripts](#npm-and-composer-scripts)
5. [Project structure](#project-structure)
6. [How the frontend works](#how-the-frontend-works)
7. [How the PHP API works](#how-the-php-api-works)
8. [Testing](#testing)
9. [CI/CD pipeline](#cicd-pipeline)
10. [Deploying](#deploying)
11. [Common tasks](#common-tasks)
12. [Troubleshooting](#troubleshooting)

---

## Requirements

| Tool | Version | Used for |
|---|---|---|
| Node.js | 20+ (CI uses 22) | frontend build, lint, tests |
| Docker + Docker Compose | recent | local nginx + PHP 8.5 + MySQL |
| Chromium for Playwright | — | E2E tests (one-time `npx playwright install chromium`) |
| PHP 8.5 + Composer | optional on the host | only to run PHP tools outside Docker |

## Quick start

```bash
git clone https://github.com/donmilorad87/blazingsun2.0.git
cd blazingsun2.0
npm install
npx playwright install chromium            # once, for E2E tests

npm run build                              # dist/ (site + packaged API)
npm run docker:up                          # nginx + PHP 8.5 + MySQL → http://blazingsun.local
docker compose exec php composer install   # PHP dev tools (PHPUnit, PHPStan) into server/vendor
npm run db:migrate                         # create table + stored procedures

npm run dev                                # http://localhost:5173, hot reload; /api goes to Docker
```

---

## Local Docker stack

`docker-compose.yml` plus `docker/` reproduce production locally:

```
browser ──► nginx :80 ────┬── /        → dist/ (built site)
                          └── /api/*   → php-fpm (PHP 8.5) → server/public/index.php ──► mysql (MySQL 26.10)
```

| Service | Image | Notes |
|---|---|---|
| `nginx` | `nginx:1.31-alpine` | Serves `dist/`; forwards `/api/*` to PHP. Config: `docker/nginx/default.conf` |
| `php` | built from `docker/php/Dockerfile` (`php:8.5-fpm-alpine` + `pdo_mysql` + Composer) | Mounts `server/`, so PHP changes apply without a rebuild. Runs as your user, so `vendor/` stays yours |
| `mysql` | `mysql:26.10` | Database `blazingsun` (app) and `blazingsun_test` (integration tests). Data lives in the `mysql-data` volume |

| What | Value |
|---|---|
| Site + API | http://blazingsun.local (API at `/api/…`); needs `127.0.0.1 blazingsun.local` in `/etc/hosts` |
| MySQL from the host | `127.0.0.1:3307`, user `blazingsun` / password `blazingsun` (root: `root`) |
| Local API config | `docker/php/config.local.php` (local-only credentials; migration token `local-dev-token`) |

Ports bind to `127.0.0.1` only (nginx `127.0.0.1:80`, MySQL `127.0.0.1:3307`), so the stack runs alongside the other local sites, which use their own `127.0.0.x` addresses. To change the ports, set `BLAZINGSUN_HTTP_PORT` / `BLAZINGSUN_DB_PORT` in `.env`.

### Opening it from another PC on the LAN

The shared reverse proxy (`../reverse-proxy`, the same one the rgbc sites use) serves **https://blazingsun.local** on this machine's LAN address `192.168.0.109:443`. It has its own `server` block and mkcert certificate (`certs/blazingsun.pem`), and forwards to `127.0.0.1:80`. The stack has to be running (`npm run docker:up`).

On the other PC:

1. Add to its hosts file (`/etc/hosts`, or `C:\Windows\System32\drivers\etc\hosts` on Windows): `192.168.0.109 blazingsun.local`
2. Trust this machine's mkcert CA, once (`$(mkcert -CAROOT)/rootCA.pem`). Skip this if that PC already opens the rgbc sites without a certificate warning.
3. Open https://blazingsun.local

macOS resolves `.local` names through Bonjour first, so the first load can take a few seconds there.

### Two ways to work locally

- **Hot-reload frontend:** `npm run dev` (Vite on :5173). Vite forwards `/api` to nginx on port 80, so the visit counter works too.
- **Production-like:** `npm run build`, then open http://blazingsun.local. nginx serves the built `dist/`, just like Hostinger.

### Everyday Docker commands

```bash
npm run docker:up          # start (builds the PHP image the first time)
npm run docker:down        # stop
npm run docker:logs        # follow logs of all services
npm run db:migrate         # apply migrations (safe to run any time)
npm run test:php           # PHPUnit (unit + integration) inside the php container
docker compose exec php composer analyse      # PHPStan
docker compose exec mysql mysql -ublazingsun -pblazingsun blazingsun   # SQL shell
docker compose down -v     # stop AND delete the database volume (fresh start)
```

---

## npm and Composer scripts

### npm (project root)

| Command | What it does |
|---|---|
| `npm run dev` | Vite dev server with hot reload; `/api` is forwarded to the Docker stack |
| `npm run build` | `vite build`, then `scripts/build-api.sh` copies the PHP API into `dist/api/` |
| `npm run preview` | Serves `dist/` with Vite (frontend only) |
| `npm run lint` | ESLint, then Stylelint, then html-validate |
| `npm test` | Frontend unit tests (`node:test`) |
| `npm run test:e2e` | Playwright E2E against `dist/` (run `build` first) |
| `npm run check` | Frontend lint, unit, build, E2E |
| `npm run images` | Regenerate responsive WebP/PNG/GIF variants from `images-src/` |
| `npm run deploy` | `check`, then deploy from your machine (needs `.env`) |
| `npm run docker:up` / `docker:down` / `docker:logs` | Local stack |
| `npm run db:migrate` | Run migrations on the local stack |
| `npm run test:php` | PHP tests inside Docker |

### Composer (`server/`, run inside the container: `docker compose exec php composer <script>`)

| Script | What it does |
|---|---|
| `composer lint` | `php -l` on every PHP file |
| `composer analyse` | PHPStan, level 8 |
| `composer test:unit` | PHPUnit unit suite (no database) |
| `composer test:integration` | PHPUnit integration suite (needs `TEST_DB_*`, set by Docker) |
| `composer test` | Both suites |

---

## Project structure

```
blazing_sun/
├── index.html                    The whole page. English is the static (SEO) copy.
├── src/                          FRONTEND
│   ├── main.js                   Entry point: `new App().start()`
│   ├── js/
│   │   ├── App.js                Creates and wires all components, registers the service worker
│   │   ├── ThemeSwitcher.js      Dark/light toggle; swaps logo, orb and brand name; emits `themechange`
│   │   ├── LanguagePicker.js     Flag dropdown (click, outside-click, Escape), remembers the language
│   │   ├── Translator.js         Fills [data-i18n] (text) and [data-i18n-html] (trusted markup)
│   │   ├── translations.js       en / sr / fr strings
│   │   ├── VisitCounter.js       POST /api/visits → writes the total into the hidden footer element
│   │   ├── CanvasLayer.js        Base class: full-screen canvas, animates only while active
│   │   ├── StarField.js          Parallax stars (dark theme)
│   │   ├── CloudLayer.js         Drifting clouds (light theme)
│   │   └── PreferenceStore.js    Safe localStorage wrapper
│   └── scss/                     _tokens (all colours as CSS variables), _base, _sky, _glass,
│                                 _header, _hero, _sections, _footer → main.scss
├── public/                       Copied unchanged to the web root: sw.js, manifest.json, favicon, fonts/, images/responsive/
├── images-src/                   Original images (not deployed); `npm run images` generates the responsive variants
│
├── server/                       PHP 8.5 API (deployed to public_html/api/)
│   ├── public/
│   │   ├── index.php             Front controller: loads config, registers routes, handles the request
│   │   └── .htaccess             Sends every /api request to index.php; nothing else is reachable
│   ├── routes/api.php            Route table (path → controller)
│   ├── src/                      namespace BlazingSun\Api\
│   │   ├── Application.php       Runs the router; turns unexpected errors into a logged, generic 500
│   │   ├── autoload.php          PSR-4 autoloader for production (no vendor/ on the server)
│   │   ├── Config/               Config (validated settings), ConfigException
│   │   ├── Database/             Connection (lazy PDO, calls procedures), SqlMigrationRunner
│   │   ├── Http/                 Request, Router, JsonResponse
│   │   ├── Controllers/          VisitController, HealthController, MigrationController
│   │   └── Models/PageVisit.php  Visit counter, via stored procedures
│   ├── migrations/               *.sql, all run on every deploy (must be idempotent)
│   ├── config/config.example.php Template; the real config.php is git-ignored / written at deploy
│   ├── tests/Unit, tests/Integration   PHPUnit
│   ├── composer.json, phpunit.xml, phpstan.neon
│
├── docker-compose.yml            Local stack (nginx + PHP-FPM + MySQL)
├── docker/
│   ├── nginx/default.conf        Site + /api routing
│   ├── php/Dockerfile            PHP 8.5-FPM + pdo_mysql + Composer
│   ├── php/config.local.php      Local API config (Docker credentials only)
│   └── mysql/init/               SQL run once on a new database volume (creates the test DB)
│
├── tests/                        FRONTEND tests: unit/ (node:test), e2e/ (Playwright)
├── scripts/
│   ├── build-api.sh              Copies the runtime PHP files into dist/api/
│   ├── write-api-config.sh       Writes config.php from env vars (safely escaped with var_export)
│   ├── deploy.sh                 Config, FTPS upload, migrations, health check
│   └── ci/                       summary.sh (job summaries), api-smoke-test.sh (HTTP checks)
├── .github/workflows/ci-cd.yml   The pipeline
├── vite.config.js                Build, /api dev proxy, service-worker version stamping
├── playwright.config.js, eslint.config.js, .stylelintrc.json, .htmlvalidate.json, .editorconfig
└── .env.example                  Template for local deploy credentials
```

---

## How the frontend works

**Startup.** An inline script in `<head>` applies the saved light theme before the first paint. `App.start()` then:

1. Starts the star and cloud canvases. Only the one for the current theme animates.
2. Restores the theme and the language.
3. Records the visit with `VisitCounter`.
4. Registers the service worker (production only).

**Theming.** Every colour lives in `src/scss/_tokens.scss`: dark values on `:root`, light overrides on `:root.light`. Components only use variables.

**Translations.** Translatable elements in `index.html` carry `data-i18n="key"`, or `data-i18n-html` when the text contains `<em>`/`<b>`. `src/js/translations.js` holds the same keys for en, sr and fr.

**Offline.** `public/sw.js` serves pages network-first and assets stale-while-revalidate. At build time its cache version is stamped as `<version>-<commit>`, so every deploy refreshes returning visitors' caches. API calls are `POST`, which the service worker never caches.

### Responsive images and fonts

- **Images:** the sun/moon orb, the header logo, the theme toggle and the flags are all `<picture>` elements: a WebP `<source>` plus a PNG/GIF fallback `<img>`, each with a `srcset` of several widths and a `sizes` hint, so the browser downloads only the file that fits the slot. The sun stays animated as WebP (1.3 MB at full size instead of the 3.9 MB GIF), and a 24px icon loads a 0.7–47 KB file instead of the full image.
  - Originals live in `images-src/` and are not deployed. The widths per image are defined once in `src/js/imageSets.js`, which the page, the theme/language switchers, the generator and the tests all share.
  - After changing a source image, run `npm run images` (uses `sharp`, dev-only) to regenerate `public/images/responsive/`, and commit the result.
- **Fonts:** self-hosted WOFF2 files in `public/fonts/`: *JetBrains Mono* (labels, dates, tech lists) and *Instrument Serif* italic (the quote and *digital* in the hero). Both are under the SIL Open Font License; the license texts sit next to the files. Latin-ext files load only when characters like č, ć, š, ž or đ appear.
- **Glass:** one style for every card, the header, the footer and the language menu: `--glass-*` tokens in `_tokens.scss`, very transparent (4–6%) with a 6px blur. Cards add a light-catching gradient edge, a cursor spotlight (`GlassSpotlight.js`), corner ticks on hover and a staggered rise-in (`Reveal.js`; off with reduced motion).

### Checking the visit counter

The footer contains:

```html
<span class="site-footer__visits" data-visit-counter hidden data-visits="1234">1234</span>
```

It's invisible on the page and to screen readers. To read it, open DevTools → Elements and search for `data-visit-counter`, or run `document.querySelector('[data-visit-counter]').dataset.visits` in the Console.

---

## How the PHP API works

### Request flow

```
POST /api/visits
  → .htaccess (Hostinger) / nginx (Docker) → public/index.php
  → Config::fromFile(config/config.php)          validated; missing keys → 503
  → routes/api.php registers routes on Router
  → Application::handle(Request)                  exceptions → error_log + generic 500
  → Router → VisitController::record
  → PageVisit::record() → CALL sp_page_visit_record()
  ← {"total": 1234}   (Content-Type: application/json, Cache-Control: no-store)
```

### Endpoints

| Method + path | Purpose | Response |
|---|---|---|
| `POST /api/visits` | Count one page load | `{"total": N}` |
| `GET /api/health` | Deploy smoke test: database reachable; reads the total without counting | `{"status":"ok","database":"ok","visits":N}` |
| `POST /api/migrations` | Run migrations (no SSH on Hostinger). Needs `Authorization: Bearer <MIGRATION_TOKEN>` | `{"applied":["001_…sql"]}`, or `401` / `404` if no token is configured |
| anything else | — | `404 {"error":"Not found"}` / `405` with an `Allow` header |

### Database

All database access goes through **stored procedures** created by migrations. The application never sends ad-hoc SQL, and `Connection::callScalar()` only accepts names matching `sp_[a-z0-9_]+`.

| Object | Definition |
|---|---|
| `page_visits` table | `visit_date DATE PK`, `visits INT UNSIGNED`, `updated_at`. One row per day, so the total is the sum and daily history comes for free |
| `sp_page_visit_record()` | Adds 1 to today's row (insert or update) and returns the all-time total |
| `sp_page_visit_total()` | Returns the all-time total |

**Migrations** (`server/migrations/*.sql`) all run, in name order, on every deploy. Every migration **must be idempotent**: use `CREATE TABLE IF NOT EXISTS`, `DROP PROCEDURE IF EXISTS` + `CREATE PROCEDURE`, `ADD COLUMN IF NOT EXISTS`, and so on. Separate the statements in a file with a line containing only `-- statement-break`, because procedure bodies contain `;`.

### PHP 8.5 features used

| Feature | Where |
|---|---|
| **Pipe operator** `\|>` | `Request::path()` (parse, strip `/api`, trim) |
| **`Uri\Rfc3986\Uri`** (new built-in URI extension) | `Request::path()` parses the request URI by the RFC 3986 standard |
| **`clone($obj, [...])`** (clone with) | `JsonResponse::withHeader()` on a readonly class |
| **`#[\NoDiscard]`** | `Router::dispatch()`, `Application::handle()`, `Config::fromArray()`. Ignoring their result is a bug |
| **`(void)` cast** | tests that intentionally ignore a `#[\NoDiscard]` result |
| Property hooks (8.4) | `Connection::$pdo` connects lazily on first access |
| `final readonly class`, typed class constants (8.2/8.3) | Config, Request, JsonResponse, controllers, model |

### Configuration and security

- `config/config.php` returns an array (DB credentials plus the migration token). It is **never committed**. Locally Docker uses `docker/php/config.local.php`; in production the deploy writes it from GitHub secrets with `scripts/write-api-config.sh`. That script escapes values with `var_export`, so a password containing quotes or `$` can't break the file.
- The config exists in `dist/` only while the upload runs, and is never part of a build artifact.
- `.htaccess` routes every `/api/...` request to `index.php`, so `config/`, `src/` and `migrations/` can't be downloaded (tested: `/api/config/config.php` returns 404).
- Clients only ever see generic error messages. The details go to the PHP error log.
- The migrations endpoint compares the token with `hash_equals` and returns 404 when no token is configured.
- Anyone can `POST /api/visits`, so the count can be inflated on purpose. That's fine for a private counter; add rate limiting if it ever matters.

---

## Testing

| Suite | Tool | Runs on | What it checks |
|---|---|---|---|
| Frontend unit | `node:test` | Node | Translations complete and in sync with the HTML; PreferenceStore; language whitelist |
| Frontend E2E | Playwright (Chromium desktop + Pixel 7) | built `dist/` | Rendering, images, nav, theme, languages, menus, mobile layout, service worker, and the hidden visit counter (API mocked) |
| PHP unit | PHPUnit 12 | PHP 8.5 | Config validation, URI/path parsing, bearer tokens, router 404/405, JSON headers, migration auth, 500 handling |
| PHP integration | PHPUnit 12 | PHP 8.5 + MySQL | Migrations idempotent; procedures exist; counting, daily rows, totals |
| API smoke | `scripts/ci/api-smoke-test.sh` | `php -S` + MySQL over HTTP | Every endpoint, status code and body; config not reachable; `no-store` header |
| PHP static | `php -l`, PHPStan level 8 | PHP 8.5 | Syntax, types, discarded `#[\NoDiscard]` results |

```bash
npm run check                                  # frontend: lint + unit + build + E2E
npm run test:php                               # PHP unit + integration (Docker)
docker compose exec php composer analyse       # PHPStan
```

---

## CI/CD pipeline

Workflow file: `.github/workflows/ci-cd.yml`.

### When it runs

**Tests run on every change. The site is deployed only when you push a release tag.**

| Event | Name in the Actions list | Tests | Deploys |
|---|---|---|---|
| **Pull request into `master`**: opened, new commits, reopened, or draft marked "Ready for review" | `🔎 PR #12: <PR title>` | ✅ | ❌ |
| **PR merged into `master`** (merge, squash or rebase) | `✅ master: <commit message>` | ✅ | ❌ |
| **Direct push to `master`** | `✅ master: <commit message>` | ✅ | ❌ |
| **Release tag pushed**: `vX.Y.Z` | `🚀 Release v1.2.0` | ✅ | ✅ only if every test **and** the release check pass |
| **Manual** (Actions → CI/CD → Run workflow) on a **branch** | `🔁 Manual checks on master by <you>` | ✅ | ❌ |
| **Manual** on a **tag** (choose the tag under "Use workflow from") | `🚀 Release v1.2.0` | ✅ | ✅ redeploys that release |

### Pull request workflow

Use pull requests to test changes on GitHub before they reach `master`:

```bash
git checkout -b feature/new-section        # work on a branch, not on master
# …edit, commit…
git push -u origin feature/new-section     # then open a PR into master on GitHub
```

1. **PR opened, or new commits pushed to it:** `🔎 PR #N: …` runs Lint, Unit tests, PHP API, Build and E2E. Results show on the PR page under **Checks** and in the run's **Summary**.
2. **More commits pushed to the PR:** the running check is cancelled and restarted on the newest code.
3. **PR merged into `master`:** `✅ master: …` runs every test again on the merged code. **Nothing is deployed.** `master` collects tested changes until you decide to release.
4. **PR closed without merging:** nothing runs.
5. **Ready to go live?** Bump the version and push a tag; see [Releasing](#releasing-deploying-to-the-live-site).

To make sure only green PRs can be merged, see the optional branch rule under [GitHub repository secrets](#github-repository-secrets).

### Jobs

```
🔍 Lint ──────────────────────────────────┐
🧪 Unit tests ────────────────────────────┤
🐘 PHP API (PHP 8.5 + MySQL 26.10) ───────┤
📦 Build ──→ 🎭 E2E (Playwright) ─────────┼──→ 🚀 Deploy to blazingsun.space
🏷️ Release check (tags only) ─────────────┘        (tags only, everything green)
```

| Job | Runs on | Steps | Shown in the run summary |
|---|---|---|---|
| 🔍 **Lint** | everything | ESLint, Stylelint, html-validate (all three always run) | ✅/❌ per linter with output |
| 🧪 **Unit tests** | everything | `node --test` with coverage; tests of the release rules (`tests/ci/check-release.test.sh`) | Test lists and coverage table |
| 🐘 **PHP API** | everything | `composer validate`, `php -l`, PHPStan level 8, PHPUnit unit, **API smoke test** over HTTP, PHPUnit integration on MySQL 26.10, `docker compose config` | ✅/❌ per step with output |
| 📦 **Build** | everything | `vite build` and the packaged API; verifies the service-worker version; uploads `dist/` | Every file with its size |
| 🎭 **E2E** | everything | Playwright on the built `dist/`, desktop and mobile | Results plus the `playwright-report` artifact |
| 🏷️ **Release check** | tags only | `scripts/ci/check-release.sh`: tag is `vX.Y.Z`, equals `package.json` version, is higher than every earlier release, and its commit is on `master` | Release notes (commits since the previous tag), or why the tag was rejected |
| 🚀 **Deploy** | tags only | Writes `api/config/config.php` from secrets, uploads the **tested** `dist/` over FTPS, runs `POST /api/migrations`, checks `GET /api/health`, smoke-tests the live `sw.js` | Release, uploaded files, migration and health results |

Deploys never overlap. Outdated PR runs are cancelled. Deploys appear under **Environments → production**.

### GitHub repository secrets

Add these under **Settings → Secrets and variables → Actions → New repository secret**:

| Secret | Value | Notes |
|---|---|---|
| `FTP_HOST` | FTP hostname/IP from hPanel → FTP Accounts | |
| `FTP_USER` | FTP username from hPanel | |
| `FTP_PASSWORD` | FTP password from hPanel | |
| `FTP_REMOTE_DIR` | path of the site's web root, relative to the FTP login folder | on Hostinger: `domains/<your-domain>/public_html`, not the top-level `public_html` |
| `DB_HOST` | `localhost` | PHP runs on the same server as MySQL (the remote hostname in hPanel is only for outside access) |
| `DB_PORT` | `3306` | |
| `DB_NAME` | database name from hPanel → Databases | |
| `DB_USER` | database user from hPanel → Databases | |
| `DB_PASSWORD` | database password from hPanel | |
| `MIGRATION_TOKEN` | output of `openssl rand -hex 32` | protects `POST /api/migrations` |

Optional: **Settings → Branches → Add rule for `master`**, then require the checks *Lint*, *Unit tests*, *PHP API* and *E2E* before merging.

---

## Deploying

### One-time Hostinger setup

1. **hPanel → Websites → blazingsun.space → Advanced → PHP Configuration**, and select **PHP 8.5**. The API uses PHP 8.5 syntax and won't run on older versions.
2. Create the database and its user in hPanel → Databases (already done for this site). The deploy creates the table and procedures itself.
3. Add the [secrets](#github-repository-secrets).

### Releasing (deploying to the live site)

**Only a pushed release tag deploys.** Pushing or merging to `master` only runs the tests.

A release tag is `v` + the `"version"` in `package.json`, always three numbers: `vMAJOR.MINOR.PATCH`, for example `v1.2.0`. The **🏷️ Release check** job rejects a tag (and nothing is deployed) if:

| Rule | Example of a rejected tag |
|---|---|
| The tag must look like `vX.Y.Z` | `v1.0`, `v.1.0`, `1.0.0`, `v1.0.0-beta` |
| It must equal the `package.json` version at that commit, so the version was bumped | `v1.1.0` while `package.json` still says `1.0.0` |
| It must be higher than every earlier release | `v1.0.4` after `v1.0.5` exists |
| Its commit must already be on `master` | a tag on a feature branch |

**Recommended: one command bumps and tags.** Start on an up-to-date `master` with all your changes merged:

```bash
git checkout master && git pull

npm version patch          # 1.0.0 → 1.0.1  bug fixes
npm version minor          # 1.0.1 → 1.1.0  new features
npm version major          # 1.1.0 → 2.0.0  big/breaking changes
# npm version edits package.json, commits "1.1.0" and creates the tag v1.1.0

git push origin master --follow-tags     # pushes the bump commit AND the tag
```

This starts two runs: `✅ master: 1.1.0` (tests only) and `🚀 Release v1.1.0` (tests, release check, deploy).

**Manual alternative.** Same result, step by step:

```bash
git checkout master && git pull
# 1. Bump "version" in package.json (e.g. "1.0.0" → "1.1.0"), then:
git commit -am "Release 1.1.0"
git push origin master
# 2. Tag that commit and push the tag:
git tag -a v1.1.0 -m "v1.1.0: <what changed>"
git push origin v1.1.0
```

**First release after setting this up:** `package.json` says `1.0.0`, so the first release tag is `v1.0.0`:

```bash
git checkout master && git pull
git tag -a v1.0.0 -m "v1.0.0: first release"
git push origin v1.0.0
```

**If a release is rejected or fails:** fix the problem, then release a **new** version (`npm version patch` and push again). To retry the same tag after a temporary failure (e.g. FTP timeout), use **Re-run failed jobs** on that run.

### Redeploy a release

**Actions → CI/CD → Run workflow → Use workflow from: Tags → `v1.1.0`**, or with the GitHub CLI:

```bash
gh workflow run ci-cd.yml --ref v1.1.0
```

Only the **latest** release can be redeployed. To go back to older code, revert it on `master` and release a new version; this prevents accidental rollbacks.

### Useful tag commands

```bash
git tag                                # list local tags
git ls-remote --tags origin            # list tags on GitHub
git show v1.0.0                        # see what a tag points to
git tag -d v1.0.0                      # delete a local tag
git push origin --delete v1.0.0        # delete a tag on GitHub
git checkout v1.0.0                    # look at a release's code (read-only; go back with: git checkout master)
```

### Deploy from your machine

```bash
cp .env.example .env       # once; fill in FTP_PASSWORD, DB_PASSWORD, MIGRATION_TOKEN
npm run deploy             # check, then config, upload, migrations, health check
```

### What ends up on the server

```
public_html/
├── index.html, assets/, images/, sw.js, manifest.json, favicon.png   ← frontend
└── api/
    ├── .htaccess, index.php                                         ← entry point
    ├── src/, routes/, migrations/                                   ← unreachable over HTTP
    └── config/config.php                                            ← written at deploy from secrets
```

Uploads overwrite files but never delete them. Old hashed `assets/*` files pile up harmlessly.

---

## Common tasks

**Add an API endpoint**

1. Write a PHPUnit test first.
2. If it needs data, write a migration in `server/migrations/NNN_name.sql` with the table changes and an `sp_…` procedure.
3. Add a model method that calls `Connection::callScalar('sp_…')`.
4. Add a controller.
5. Register the route in `server/routes/api.php`.
6. Add a line to `scripts/ci/api-smoke-test.sh`.

**Change the database**

Add a new idempotent migration file; never edit one that's already deployed. Then run `npm run db:migrate` locally, and `npm run test:php`.

**Change or add text**

Edit `index.html` (add `data-i18n` if it should be translated), add the key to all three languages in `src/js/translations.js`, then run `npm test`.

**Change colours**

Edit `src/scss/_tokens.scss` only.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `npm run dev`: visit counter warning `[VisitCounter] could not record visit` | Start Docker (`npm run docker:up`) and run `npm run db:migrate` |
| `http://blazingsun.local` shows nginx 404 / old site | Run `npm run build`; nginx serves `dist/` |
| API returns `503 Service unavailable` | `config.php` missing or incomplete: check the secrets, or `docker/php/config.local.php` locally |
| API returns `500` | Read the PHP error log (`npm run docker:logs` locally; hPanel → Error logs in production) |
| `composer test:integration` skips everything | `TEST_DB_*` isn't set. Run it inside Docker (`npm run test:php`) |
| Integration tests can't access `blazingsun_test` | The DB volume predates `docker/mysql/init/`. Run `docker compose down -v` and start again |
| Pushed to `master` but the site didn't change | Expected: `master` only runs tests. Push a release tag; see [Releasing](#releasing-deploying-to-the-live-site) |
| 🏷️ Release check failed | Read its summary; the tag isn't `vX.Y.Z`, doesn't match `package.json`, isn't higher than the last release, or isn't on `master` |
| Deploy: `Migrations failed` / `Health check failed` | Wrong DB secrets, PHP not set to 8.5 in hPanel, or `MIGRATION_TOKEN` mismatch. The step log shows the API's response |
| Deploy times out connecting to FTP | Hostinger may be blocking GitHub's servers. Deploy locally with `npm run deploy` |
| Smoke test ⚠️ "HTTP 403" | Hostinger's bot check blocked the request; the upload succeeded |
| E2E: "Executable doesn't exist" | `npx playwright install chromium` |
| Stylelint complains about colour notation | `npx stylelint "src/**/*.scss" --fix` |
