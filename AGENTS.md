# AGENTS.md - rules for AI agents working on CAIRS

CAIRS (Community Assistance and Incident Reporting System) is a PHP + MySQL web app
for a barangay. Residents report incidents and send SOS alerts, admins assign
volunteers and track progress, and everyone receives notifications.

## Stack contract (do not change)

This project is **PHP 8+ with MySQL/MariaDB and plain CSS**. Do not introduce
Node.js, npm/bun, EJS, React/Vue, TypeScript, bundlers, Tailwind, Sass/Less, or any
build step. The system must keep running by simply serving the folder with
Apache/Laragon - no compile or install commands.

## Allowed file types

- **Application code: `.php` only.** Every page, partial, handler, API endpoint and
  server-side file must be `.php`.
- **Browser JavaScript must be delivered from a `.php` file.** `assets/js/script.php`
  sends `Content-Type: application/javascript` and then outputs plain JS. It is
  loaded with `<script src="<?= BASE_URL ?>/assets/js/script.php"></script>` from
  `includes/footer.php`. Keep this pattern. Never add `.js`, `.mjs`, `.ts`, or `.jsx`.
- **Styles: `.css` only** (`assets/css/style.css`). No preprocessors, no CSS-in-JS.
- **Support ("hack") files that are allowed:** `.htaccess`, `*.sql`
  (`database/cairs.sql`), `.gitkeep`, `.md` documentation, and static media
  (images/fonts) under `assets/`.
- **Never add** `package.json`, `bun.lock`, `node_modules/`, `server.js`,
  `views/*.ejs`, console scripts, or build configuration.

## How the app is wired

- `includes/header.php` and `includes/footer.php` render the shared layout.
- `includes/helpers.php` holds shared helpers (`e()`, `pdo()` wrappers, UI
  renderers). Reuse them instead of adding libraries.
- `config/database.php` holds DB settings, auto-creates the `cairs` database and
  schema on first load, seeds demo accounts, and auto-detects `BASE_URL`.
  Do not break this zero-setup behavior (or the Laragon workflow).
- Role pages live in `admin/`, `resident/`, and `volunteer/`; AJAX endpoints live
  in `api/*.php` and are called over `fetch` from `assets/js/script.php`.

## Conventions

- Start PHP files with `declare(strict_types=1);`.
- Use PDO prepared statements; never concatenate user input into SQL.
- Escape output with `e()`; protect forms with `csrfField()` / `csrfCheckOrDie()`;
  enforce roles with `requireRole()`.
- Use the existing icons, badges, cards and helpers instead of adding new assets.
- After any change, run `php -l` on modified PHP files and smoke-test the pages.

## Never

- Never rename application files to `.html`, `.js`, `.ejs`, or extensionless URLs.
- Never add client-side code as a raw `.js` file.
- Never commit secrets, `.env`, uploaded photos, or real credentials. The default
  `root` / empty password in `config/database.php` is for local development only.
