# Admin portal, CMS, languages and schema

Everything on blazingsun.space is edited at **`/adminportal`**. There is no static `index.html` any more: PHP renders the page from the database for every language and caches it, and every save in the admin updates the site.

1. [Setting up the login](#1-setting-up-the-login)
2. [Using the portal](#2-using-the-portal)
3. [Languages](#3-languages)
4. [Images](#4-images)
5. [Schema graph (JSON-LD)](#5-schema-graph-json-ld)
6. [How it works](#6-how-it-works)
7. [Security](#7-security)
8. [Developer notes](#8-developer-notes)

---

## 1. Setting up the login

There is **one admin**. The username and password are **not stored in the database**: they come from the server's config file (`config.php`), which holds only a **hash** of the password, never the password itself.

### Locally (Docker)

The local login comes from `ADMIN_USERNAME` / `ADMIN_PASSWORD` in `.env` (passed into the PHP container by `docker-compose.yml`). When they are empty, the defaults below apply:

| | |
|---|---|
| URL | http://blazingsun.local/adminportal/ (or http://localhost:5173/adminportal/ while `npm run dev` runs) |
| Username | `blazingsun` |
| Password | `blazingsun` |

Steps from scratch:

```bash
npm run build          # builds the site and the admin portal into dist/
npm run docker:up      # nginx + PHP + MySQL
npm run db:migrate     # creates the tables and procedures, and seeds the original content (only the first time)
# open http://blazingsun.local/adminportal/
```

After changing them in `.env`, run `docker compose up -d php` to apply. Locally there is no minimum length; the deploy requires 12+ characters.

### `.env` (deploying from your machine)

`.env` and `.env.example` list every setting. For the admin portal, fill in:

```dotenv
# Plain password, at least 12 characters: the deploy writes only its hash to the server config.
ADMIN_USERNAME=milorad
ADMIN_PASSWORD='a long password with spaces or $ in single quotes'
# Optional: defaults to https://blazingsun.space
SITE_URL=
```

You type the **plain password**. You do **not** hash it yourself. While deploying, `scripts/write-api-config.sh` turns it into an argon2id hash and writes only that hash into `public_html/api/config/config.php`. The deploy refuses a password shorter than 12 characters. Leave `ADMIN_USERNAME` empty to switch the admin login off.

### Production (GitHub Actions)

In GitHub: **Settings → Secrets and variables → Actions → New repository secret**, add:

| Secret | Value |
|---|---|
| `ADMIN_USERNAME` | your login name |
| `ADMIN_PASSWORD` | your password (plain, at least 12 characters) |

The next release deploy (`vX.Y.Z` tag) writes them into the server config. **To change the password**, update the secret and deploy again (or redeploy the current release: Actions → CI/CD → Run workflow on the tag).

Every other secret is listed in the [README](../README.md#github-repository-secrets).

---

## 2. Using the portal

Log in, then pick a screen in the sidebar. The URL keeps the screen (`/adminportal/#experience`), so reloading stays on it.

| Screen | What you edit |
|---|---|
| **Header & menu** | Site name for the dark and the light theme · logo for each theme · icon for each theme (the same icon is the theme switch button **and** the big sun/moon in the home banner) · **Main menu**: which sections appear in the menu, and each one's label in every language |
| **Home banner** | Before title, title, description, buttons (label, link, primary/ghost style) and the small info line under them. It is not in the main menu |
| **Profile** | Section heading · stats (value + label) · profile description (the quote) · company description · company details (the tags) |
| **Capabilities** | Section heading · cards (title + text) |
| **Experience** | Section heading · jobs (period, role, company, description) |
| **Selected work** | Section heading · projects (category, name, description, technologies, optional link) |
| **Expanded CV** | Section heading · cards (title + text) |
| **Contact** | Section heading · links (type phone / e-mail / link, the value, optional shown text, an icon character) |
| **Footer** | Your name, company, position, base country. Shown as `© {year} {name} · {company}` and `{position} · {country}`; the year updates by itself |
| **Languages & labels** | Add, edit, delete languages and choose the main one · **Labels**: page title, meta description and screen-reader texts, per language |
| **Schema graph** | A read-only picture of the structured data (JSON-LD) in the page |

**Section heading.** Every section except the home banner has *before title*, *title* and *description*. The number in front of the before title (`03 / Experience`) is added automatically from the section's position, so you only write `Experience`.

**Sections are fixed.** You can edit them, their items and their menu labels, but you can't add or remove sections.

**Items.** Each repeatable entry is a card with **↑ / ↓** (move) and **Remove**, plus **+ Add to …** at the end of the list. Nothing changes on the site until you press **Save**: then the order you see becomes the order on the page, removed cards are deleted and new ones are created.

**Formatting.** Text that allows it (home banner title, capability texts) has **I** and **B** buttons: select words and click. *Italic* is the gold accent (`Building <em>digital</em> systems`). No other HTML is accepted.

**Saving and errors.** Save checks everything on the server. Invalid fields turn red with a message under them, and the form switches to the language that has the problem. Nothing is saved until the whole form is valid. When the save works, a "Saved. The site is updated." notice appears and the site shows the change on the next page load.

**Unsaved changes.** If you try to leave or reload with unsaved edits, the browser asks you first.

**Session.** You stay logged in while you work; after 2 hours without activity you have to log in again. **Log out** is at the top right.

---

## 3. Languages

### URLs

| Language | URL |
|---|---|
| The **main** language (English now) | `/` |
| Every other language | `/{iso2}/`, e.g. `/sr/`, `/fr/` |

`/en/` (the main language's own code) redirects to `/`. A code that isn't a language returns 404. The flag menu in the header links to every language's page.

Each page has, for search engines:

- `<html lang="sr">`
- `<link rel="canonical" href="https://blazingsun.space/sr/">`
- `<link rel="alternate" hreflang="…">` for every language, plus `hreflang="x-default"` pointing at the main language

### Translating

Forms show one language at a time: switch with the **language tabs** at the top of the form. A red number on a tab is how many **required** texts are still empty in that language.

An empty translation is not an error: **the page shows the main language's text instead** (the empty input shows that text as a grey placeholder). So you can add a language and translate it bit by bit.

Fields that are the same in every language (links, company names, stat values, images) appear once, in every tab.

### Adding a language

**Languages & labels → Add a language**:

| Field | Example | Notes |
|---|---|---|
| ISO 639-1 code | `de` | two letters; becomes the URL `/de/`; can't be changed later |
| ISO 639-2 code | `deu` | three letters, unique |
| Name | `Deutsch` | in the language itself; shown in the flag menu |
| Flag | an `.svg` file | SVG only; cleaned on upload (see [Images](#4-images)) |

Save, and `/de/` exists immediately (showing the main language's texts until you translate them). Up to 20 languages.

### Main language, editing, deleting

- **Make main**: the language moves to `/` and becomes the fallback for every untranslated text. This is only allowed once **all its required texts are translated**, so the site can never show empty headings. The list shows how many are missing.
- **Edit**: change the ISO 639-2 code, the name or the flag (the ISO 639-1 code stays).
- **Delete**: removes the language **and all its translations** (after a confirmation). The main language can't be deleted; make another language main first.

### Labels

Texts that don't belong to a section, per language: the page **title** (browser tab, search results), the **meta description**, and the screen-reader labels of the main menu, the theme switch and the back-to-top button.

---

## 4. Images

Image fields show the current image, **Upload new image…** and **Choose an uploaded image** (every image uploaded so far). Uploading doesn't change the site until you **Save** the form.

| You upload | What happens |
|---|---|
| PNG, JPEG, WebP | Resized on the server to the same sizes the site already uses (24 – 426 px wide, never enlarged), as WebP plus a PNG fallback. Browsers pick the right size. |
| Animated GIF or animated WebP | Kept exactly as uploaded (resizing would lose the animation). This is how the animated sun works. |
| SVG | Cleaned: scripts, event handlers, external links and CSS that loads anything are removed. |

Limits: 8 MB, 4096 × 4096 pixels. Language flags must be SVG.

The header has four images: a **logo** and an **icon** for each theme. The logo is the small image next to the site name. The icon is used twice: on the theme switch button and as the big sun/moon in the home banner.

On the server, uploads live in `public_html/uploads/media/` (locally `uploads/media/`). Deploys never touch them. If the host's PHP can't write WebP, uploads get PNG only, and `/api/health` shows `"webp": false`.

---

## 5. Schema graph (JSON-LD)

Each page's `<head>` contains structured data for search engines: one `@graph` whose nodes point at each other by `@id`:

```
WebPage ──isPartOf──▶ WebSite ──publisher──▶ ProfessionalService (Blazing Sun)
   │                                             │          ▲
   └──────────────about──────────────▶ Person ◀──founder──┘  │
                                         └──────worksFor──────┘
```

| Node | `@id` | Contents |
|---|---|---|
| **Person** | `https://blazingsun.space/#person` | Unchanged from before: name, job title, description, photo, phone, e-mail, address, LinkedIn/GitHub, `worksFor` → company |
| **ProfessionalService** | `https://blazingsun.space/#company` | The company as a schema.org **LocalBusiness** (ProfessionalService is its subtype for consulting/software businesses): name, logo, phone, e-mail, address, MB and PIB, `founder` → Person |
| **WebSite** | `https://blazingsun.space/#website` | The site, its languages, `publisher` → company |
| **WebPage** | `{page URL}#webpage` | One per language page: title, description, `inLanguage`, `isPartOf` → WebSite, `about` → Person |

The **Schema graph** screen draws these nodes and links and shows the raw JSON. The Person and company data are fixed in `server/src/Page/SchemaGraph.php` (they are not editable in the portal); the page title, description and languages come from the CMS. To check it, paste a page URL into Google's [Rich Results Test](https://search.google.com/test/rich-results) or the [Schema Markup Validator](https://validator.schema.org/).

---

## 6. How it works

```
Visitor ── GET /sr/ ──▶ .htaccess / nginx ──▶ api/page.php
                                              ├─ cached page for "sr"? → send it (no database)
                                              └─ else: load content (stored procedures) → render templates/page.php
                                                       → write the cache → send it

Admin ── POST /api/admin/section ──▶ api/index.php ──▶ login + CSRF check ──▶ validate ──▶ save (one transaction)
                                                                                              └─ clear the page cache
```

- **Content model.** Sections and their fields are defined once in `server/src/Content/Definitions.php`. The API validates against it and the portal builds its forms from it. Every value is one database row: *where* (`section:about`, `item:12`, `header`, `footer`, `labels`), *which field*, *which language* (empty for values that aren't translated).
- **Stored procedures.** The PHP code never sends raw SQL; every read and write is a `CALL sp_…` defined in `server/migrations/002_create_content.sql`.
- **First deploy.** The migration step creates the tables, then fills the CMS **once** with the original page in English, Serbian and French (`server/content/seed.json`). Later deploys never re-seed, so items you delete stay deleted.
- **Cache.** Rendered pages are files in `api/storage/cache/`. Every admin save deletes them; the next visit renders again (milliseconds). A deploy changes the CSS/JS file names and the year is part of the key, so stale pages are never served.
- **Offline (PWA).** The service worker loads pages from the network first and keeps each language page for offline use, so admin changes show on a normal reload.

---

## 7. Security

| Risk | Protection |
|---|---|
| Password guessing | 5 failed logins per IP in 15 minutes, then blocked (`429`) for 15 minutes. Passwords are compared with argon2id hashes in constant time. |
| Stolen password from the server | Only the hash is on the server; the plain password exists only in your GitHub secret / `.env`. |
| Session hijacking | Cookie is `HttpOnly`, `SameSite=Strict`, `Secure` over HTTPS and only sent to `/api/admin`; a new session id at login; 2-hour idle timeout. |
| Forged requests from other sites (CSRF) | Every change needs the `X-CSRF-Token` that only the logged-in portal knows. |
| Script injection through content | All text is HTML-escaped; only `<em>`/`<b>` are restored. Links must be `https://`, `http://`, `mailto:`, `tel:` or `#section`. |
| Malicious uploads | Type checked from the file's content; images re-encoded; SVGs cleaned; `/uploads/` never executes code and is served with a `sandbox` CSP. |
| Search engines indexing the portal | `noindex, nofollow` meta tag and `X-Robots-Tag` header. |

---

## 8. Developer notes

- **Add an editable field**: add it to `Definitions.php` and print it in `server/templates/page.php`. The form field appears by itself.
- **Tests**: `npm run test:e2e` runs the public page tests (desktop + mobile) and then the admin tests (`tests/e2e/admin/`), which log in as `e2e-admin` / `e2e-password` against a throwaway database (`blazingsun_e2e`). PHP: `npm run test:php`.
- **Local page while developing**: with `npm run dev` running, the PHP-rendered page loads the CSS/JS from Vite (hot reload) through `server/storage/hot`.
- **Reset local content** to the original: `docker compose exec mysql mysql -uroot -proot blazingsun -e "SET FOREIGN_KEY_CHECKS=0; TRUNCATE site_meta; TRUNCATE content_values; TRUNCATE items; TRUNCATE languages; TRUNCATE media;"`, then `npm run db:migrate`.
