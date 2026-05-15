FROM node:20-slim AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
COPY .npmrc* ./
RUN npm install
COPY . .

# resources/js/plugins/iconify/icons.css is a generated artifact (gitignored).
# Drop a stub if missing so Vite/Rollup can resolve the import.
RUN test -f resources/js/plugins/iconify/icons.css \
    || echo '/* placeholder */' > resources/js/plugins/iconify/icons.css

RUN npm run build


FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath xml ctype fileinfo zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .
COPY --from=frontend /app/public/build public/build

RUN composer dump-autoload --optimize

# .dockerignore excludes the framework cache subdirs, so Laravel needs an
# empty skeleton at runtime to write into.
RUN mkdir -p storage/framework/cache/data \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/logs \
    && chmod -R 775 storage bootstrap/cache

# Increase PHP upload + memory limits
RUN echo "upload_max_filesize=512M\npost_max_size=512M\nmemory_limit=512M" > /usr/local/etc/php/conf.d/uploads.ini

RUN chmod +x docker-entrypoint.sh

EXPOSE 8080

CMD ["./docker-entrypoint.sh"]
