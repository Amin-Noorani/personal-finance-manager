# AGENTS.md

Personal Finance Manager (PFM) — single-user Persian (Farsi) web app for tracking income/expenses. PHP + MySQL + vanilla JS, no framework.

## Setup

1. Visit `/pfm/install.php` to create database tables and seed default categories (blocked by `.htaccess` after setup)
2. Visit `/pfm/setup.php` to create the first user account (also blocked after first use)
3. Login at `/pfm/login.php`

Database: MySQL via PDO. Credentials in `config/database.php` (user: `pfm_user`). No `.env` — config is hardcoded.

## Architecture

- **No framework** — raw PHP files, one file = one page
- **PDO MySQL** with prepared statements (`config/database.php` provides `getDB()` singleton)
- **Session auth** with CSRF tokens (`config/auth.php`)
- **Jalali (Persian) calendar** everywhere (`config/jalali.php`) — all dates stored as Gregorian in DB, displayed as Jalali in UI
- **Vazir font** loaded locally from `fonts/vazir/`
- **Chart.js** for analytics charts (vendored in `lib/chart.min.js`)
- **Persian datepicker** (vendored in `lib/persian-datepicker/`)
- **Card-based UI** — all lists use `.card-list` / `.card-item` responsive cards (not tables)

## Key Files

| File | Purpose |
|------|---------|
| `config/database.php` | DB connection (PDO singleton) |
| `config/auth.php` | Session, CSRF, brute force protection, remember-me cookies |
| `config/jalali.php` | Jalali↔Gregorian conversion (JDF algorithm) + `toPersianDigits()` |
| `includes/header.php` | Shared HTML head + navbar (set `$pageTitle`, `$activePage`, `$includeDatepicker`) |
| `includes/footer.php` | Shared scripts + closing HTML (set `$includeChart`, `$extraScripts`) |
| `js/app.js` | Mobile nav, datepicker init, category filtering, SMS parsing modal |
| `js/analytics.js` | Chart.js chart rendering (reads `analyticsData` global) |
| `css/style.css` | Main stylesheet (Vazir font, RTL, card layout, responsive) |
| `css/analytics.css` | Analytics page specific styles |

## Conventions

- **RTL layout** — `dir="rtl"` on all pages, CSS uses `margin-inline-start` etc.
- **Persian text** — all UI strings in Farsi, use `toPersianDigits()` for numbers displayed to users
- **Currency** — Toman (تومان), amounts stored as DECIMAL(12,2)
- **Date handling** — DB stores Gregorian `YYYY-MM-DD`, UI shows Jalali `YYYY/MM/DD`. Use `formatJalali()` to convert display, JS datepicker handles Jalali→Gregorian on submit
- **Category types** — categories have a `type` field: `income`, `expense`, or `both`. Transaction forms filter category dropdown by selected type
- **Balance reconciliation** — when editing/deleting transactions, always reverse the old amount first, then apply new amount to account balance. Wrap in DB transaction.
- **CSRF protection** — all POST forms must include `csrf_token` field via `generateCSRFToken()`
- **No build step** — all CSS/JS is hand-written, libraries vendored in `lib/`
- **Card layout** — lists use `.card-list` > `.card-item` > `.card-accent` + `.card-body` + `.card-actions`. Desktop: horizontal with left accent bar. Mobile: stacked vertically.

## Database Schema (7 tables)

- `users` — id, username, password_hash (bcrypt cost 12)
- `accounts` — id, name, initial_balance, current_balance (recomputed on every transaction change)
- `categories` — id, name, parent_category_id (hierarchical), is_active, type (income/expense/both)
- `tags` — id, name (unique)
- `transactions` — id, type (income/expense), date, time, account_id, amount, category_id, tag_id, description
- `login_attempts` — id, ip_address, attempted_at (brute force protection: 5 attempts / 15 min lockout)
- `remember_tokens` — id, user_id, token_hash (SHA-256), expires_at (30-day remember-me)

## Common Tasks

**Add a new page:** Create PHP file, set `$pageTitle` and `$activePage`, include header/footer. Add nav entry in `includes/header.php` `$navItems` array.

**Add a new category type filter:** Query must include `type` column, options need `data-cattype` attribute, filtering handled by `app.js` automatically.

**Add chart to analytics:** Add `<canvas>` in analytics.php HTML, add data query in PHP, pass to JS via `analyticsData` object, render in `js/analytics.js`. Use `JSON_HEX_TAG` flags when encoding JSON for inline `<script>` tags (prevents XSS via `</script>` injection).

**Modify balance logic:** Always wrap in `$db->beginTransaction()` / `$db->commit()`. Reverse old → apply new → update `accounts.current_balance`.

**Add a new card list:** Use the `.card-list` > `.card-item` structure. Each card needs a `.card-accent` (color bar), `.card-body` (fields), and optional `.card-actions` (buttons). See transactions.php for the full pattern.

## Security

- `.htaccess` blocks direct access to `config/`, `data/`, `vendor/`, `install.php`, `setup.php`
- Prepared statements on all queries
- `htmlspecialchars()` on all output
- CSRF tokens on all forms
- Password hashing with `password_hash(PASSWORD_BCRYPT, ['cost' => 12])`
- Session regeneration on login
- Brute force protection: 5 failed logins per IP → 15 min lockout
- Remember-me: DB-backed tokens (SHA-256 hashed), rotated on each auto-login
- Session cookie: `httponly`, `samesite=Lax`, `use_strict_mode`
- Security headers: CSP, X-Frame-Options, X-Content-Type-Options, HSTS (when HTTPS enabled)
- Never leak `$e->getMessage()` in user-facing error messages

## Gotchas

- **Arabic vs Persian characters** — Bank SMS uses Arabic `ي` (U+064A) and `ك` (U+0643), but Persian uses `ی` (U+06CC) and `ک` (U+06A9). The SMS parser normalizes these before regex matching.
- **Jalali conversion** — Use the JDF algorithm in `config/jalali.php`. The forward direction (`gregorianToJalali`) and inverse (`jalaliToGregorian`) must produce consistent round-trip results. If modifying, verify with known dates.
- **Analytics JSON** — Always use `JSON_HEX_TAG | JSON_HEX_AMP` flags when encoding data for inline `<script>` tags to prevent XSS.
- **Datepicker dual fields** — Jalali datepicker uses a visible input (`pwt-datepicker-input` with `data-alt`) and a hidden input for the Gregorian value submitted to the server.
