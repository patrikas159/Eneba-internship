# Production image: one container that serves the React SPA and the Symfony API from the same
# origin (so the httpOnly auth cookie needs no CORS). Used by Render; local dev uses
# docker-compose.yml + backend/Dockerfile instead.

# ---- 1. Build the frontend ----
# Debian-based (glibc) image: the native TypeScript 7 compiler ships glibc binaries.
FROM node:22-slim AS frontend
WORKDIR /src/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

# ---- 2. PHP app on FrankenPHP ----
FROM dunglas/frankenphp:1-php8.5 AS app

RUN install-php-extensions pdo_pgsql intl opcache zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deploy/php-prod.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Dependencies first so this layer is cached until composer.lock changes.
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY backend/ ./
RUN rm -f .env.local .env.*.local \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console cache:warmup \
    && php bin/console assets:install public

# Built SPA next to public/index.php (Symfony front controller).
COPY --from=frontend /src/frontend/dist/ /app/public/

COPY deploy/Caddyfile /etc/frankenphp/Caddyfile
COPY deploy/docker-entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

# Render sets PORT (default 10000); 8080 when run elsewhere.
EXPOSE 8080
ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
