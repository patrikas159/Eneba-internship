# FrankenPHP = Caddy web server + PHP in one binary. Serves /app/public by default.
FROM dunglas/frankenphp:1-php8.5

# PHP extensions the app needs. pdo_pgsql is the PostgreSQL driver Doctrine uses;
# the base image does NOT include it (only pdo_sqlite).
RUN install-php-extensions \
        pdo_pgsql \
        intl \
        opcache \
        zip

# Composer inside the container, so nobody needs PHP/Composer on their machine.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ":80" = plain HTTP on port 80; disables Caddy's automatic HTTPS for local dev.
ENV SERVER_NAME=":80"

WORKDIR /app
