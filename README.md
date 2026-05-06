# Code Review

AI-powered code review tool for vibe coders. One-shot review of any GitHub repo, with security, performance, and quality scores plus concrete fix suggestions. Users can buy a redeem code, sign in to keep history, connect GitHub to scan private repos, and download a PDF report.

## Stack

- **Backend:** Laravel 11 (PHP 8.2+)
- **Frontend:** Vue 3 + Vuetify 3 (Vuexy template, copied from Asrify)
- **Build:** Vite, unplugin-vue-router (file-based routes), unplugin-vue-components
- **DB:** PostgreSQL (manage with TablePlus)
- **Local server:** Laravel Valet → `http://codereview.test`
- **Integrations:** Stripe (one-time payments, multi-line items per category), GitHub OAuth (private repos), Anthropic Claude (analysis), DomPDF (report)

## Pricing

The single-tier $30 model was replaced with a configurable scope model. The catalog lives in [config/codereview.php](config/codereview.php) and is the single source of truth — it's surfaced via `GET /api/catalog` and consumed both by the in-app `CategoryConfigurator` and the landing page.

| Category    | Price |
| ----------- | ----: |
| Security    | $15   |
| Database    | $15   |
| Backend     | $20   |
| Frontend    | $15   |

Minimum order is $15 (any single category). Stripe checkout creates one line item per selected category. Selected category keys flow through Stripe metadata → `redeem_codes.selected_categories` → `analyses.selected_categories` and into the Anthropic prompt as a "review scope" line so the analyzer focuses on what was actually paid for.

## Setup

This project is served via [Laravel Valet](https://laravel.com/docs/valet) and uses PostgreSQL.

**1. Create the database in TablePlus**

Spin up a Postgres database called `codereview` (matching `DB_DATABASE` in `.env`).

**2. Make sure Valet sees this folder**

If you haven't already pointed Valet at the parent folder:

```sh
cd /Users/anelkujovic/Documents/Projects
valet park
```

The folder name becomes the domain, so this app will be available at **http://codereview.test** with no `php artisan serve` needed.

**3. Install dependencies and migrate**

```sh
cd /Users/anelkujovic/Documents/Projects/codereview
composer install
npm install

cp .env.example .env
php artisan key:generate
# edit .env and fill in DB_USERNAME / DB_PASSWORD for Postgres
php artisan migrate

npm run dev    # vite — leave running while developing
```

Open http://codereview.test in the browser. Vite's dev server is wired up via `laravel-vite-plugin`, so just keep `npm run dev` running.

## Required environment variables

The app boots without these, but the integrations need them to actually work:

| Group       | Variables                                                                     |
| ----------- | ----------------------------------------------------------------------------- |
| Stripe      | `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_CENTS`                 |
| GitHub      | `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITHUB_REDIRECT_URI`              |
| Anthropic   | `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` (default `claude-sonnet-4-6`)          |
| Mail        | Standard Laravel `MAIL_*` keys; defaults to `log` driver                      |

### Stripe webhook

Stripe webhook endpoint is `POST /api/stripe/webhook` and is excluded from CSRF in [bootstrap/app.php](bootstrap/app.php). Forward locally with `stripe listen --forward-to http://codereview.test/api/stripe/webhook`.

### GitHub OAuth

Set `GITHUB_REDIRECT_URI` to `${APP_URL}/api/github/callback`. The OAuth scopes requested are `repo read:user user:email` (read-only — tokens are stored on the redeem code row and used only for that one analysis).

## Routes

### Frontend (file-based via [unplugin-vue-router](https://github.com/posva/unplugin-vue-router))

| Path                | Page                                       | Auth |
| ------------------- | ------------------------------------------ | ---- |
| `/`                 | [pages/index.vue](resources/js/pages/index.vue) — purchase / redeem / analyze | public |
| `/history`          | [pages/history.vue](resources/js/pages/history.vue) | required |
| `/analyses/:id`     | [pages/analyses/[id].vue](resources/js/pages/analyses/[id].vue) | required |
| `/login`            | [pages/login.vue](resources/js/pages/login.vue) | guest |
| `/register`         | [pages/register.vue](resources/js/pages/register.vue) | guest |

### Backend ([routes/api.php](routes/api.php))

| Method | Path                                  | Description                          |
| ------ | ------------------------------------- | ------------------------------------ |
| POST   | `/api/auth/register`                  | Create account                       |
| POST   | `/api/auth/login`                     | Sign in                              |
| POST   | `/api/auth/logout`                    | Sign out                             |
| GET    | `/api/auth/me`                        | Current user                         |
| POST   | `/api/stripe/checkout`                | Start Stripe Checkout                |
| GET    | `/api/stripe/sessions/{id}/code`      | Fetch issued code after Stripe success |
| POST   | `/api/stripe/webhook`                 | Stripe webhook (issues redeem codes) |
| POST   | `/api/codes/validate`                 | Validate a redeem code               |
| GET    | `/api/codes/{code}/report.pdf`        | Download report by redeem code       |
| GET    | `/api/github/login`                   | Start GitHub OAuth (state=code)      |
| GET    | `/api/github/callback`                | OAuth callback                       |
| GET    | `/api/github/repos`                   | List repos for connected code        |
| POST   | `/api/analyses/run`                   | Run analysis (needs code OR auth)    |
| GET    | `/api/analyses/history`               | List user's past analyses            |
| GET    | `/api/analyses/{id}`                  | Single analysis                      |
| GET    | `/api/analyses/{id}/report.pdf`       | Download PDF report                  |

## How analysis works

[AnalysisService](app/Services/AnalysisService.php) orchestrates:

1. Resolve owner/repo from URL or `owner/repo`
2. Fetch repo metadata + git tree (using OAuth token if connected, else public)
3. Filter and rank files via [RepoFileSelector](app/Services/RepoFileSelector.php) — favors DB schemas, controllers, routes; deprioritizes tests/docs
4. Pull file contents up to ~850k token budget
5. Send to Claude with a system prompt (see [AnthropicService](app/Services/AnthropicService.php)) that yields a structured JSON report
6. Persist as an `Analysis` row, mark code used, email PDF report

The analyzer prompt and JSON schema match Vibe-Coach so the output is compatible.

## What's missing / known limitations

- The original Vibe-Coach analyzer chunks very large repos across multiple Claude calls and runs a synthesis pass. This port does a single Claude call up to ~850k input tokens, which is enough for most repos but won't scale to monorepos.
- No per-user redeem-code attribution UI yet — codes are emailed and can be redeemed by anyone with the code (matches Vibe-Coach behavior). If you want to lock codes to the purchaser's account, also check `user_id` on the `RedeemCode` row.
- Sentry, MSW, i18n, Reverb / Pusher, Spatie media-library, etc. were stripped from the Vuexy template; if you want them back, copy from `Asrify/`.
