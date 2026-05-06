# Production image for Full Code Review (Laravel 11 + Vite + Vue + Postgres).
#
# Two-stage build:
#   1) frontend  → Node 20 builds vite assets into public/build
#   2) runtime   → PHP 8.2 CLI + composer + the assets, run by `php artisan serve`
#
# Runs `php artisan migrate --force` at container start before booting the app.

# ─────────────────────────── Stage 1: Vite build ───────────────────────────
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
COPY .npmrc* ./
RUN npm ci --no-audit --no-fund
COPY postcss.config.js* tailwind.config.js* vite.config.js jsconfig.json themeConfig.js ./
COPY resources ./resources
COPY public ./public
COPY routes ./routes

# resources/js/plugins/iconify/icons.css is a generated bundle (built locally
# by @iconify/tools) and gitignored, so it doesn't exist in a clean checkout.
# Vite's plugin entry imports it, so the build crashes with "Could not resolve
# ./icons.css". Drop a stub so the build can proceed. NOTE: with a stub in
# place no `tabler-*` icons will render in production. To restore icons,
# either un-gitignore the real file and commit it, or regenerate it in this
# stage (requires @iconify/tools + tsx + a Node-side build script).
RUN test -f resources/js/plugins/iconify/icons.css \
    || echo '/* placeholder — icons.css is a generated artifact (gitignored). Regenerate or commit the real file to restore tabler-* icons. */' \
       > resources/js/plugins/iconify/icons.css

RUN npm run build


# ─────────────────────────── Stage 2: PHP runtime ───────────────────────────
FROM php:8.2-cli-bookworm AS runtime

# install-php-extensions handles compile + runtime libs in one shot, much
# more reliable than apt + docker-php-ext-install + manual configure.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_pgsql
RUN install-php-extensions bcmath
RUN install-php-extensions zip
RUN install-php-extensions opcache
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip ca-certificates \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Install PHP deps first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts \
    && composer clear-cache

# Bring in the rest of the source
COPY . .

# Bring in the pre-built assets from the frontend stage
COPY --from=frontend /app/public/build ./public/build

# Run composer scripts now that artisan exists
RUN composer dump-autoload --optimize --no-dev

# Container entry: migrate + cache + serve. Caches happen at runtime so the
# Railway-injected env vars are baked in.
EXPOSE 8000
CMD php artisan migrate --force \
 && php artisan config:cache \
 && php artisan route:cache \
 && php artisan view:cache \
 && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
