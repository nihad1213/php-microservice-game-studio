# syntax=docker/dockerfile:1
# (file: docker/php/Dockerfile)

ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-apache AS base
ARG SERVICE
ENV SERVICE=${SERVICE} \
    APACHE_DOCUMENT_ROOT=/app/services/${SERVICE}/public

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf \
    && sed -ri "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/conf-available/*.conf \
    && printf "<Directory ${APACHE_DOCUMENT_ROOT}>\n Require all granted\n FallbackResource /index.php\n</Directory>\n" \
       > /etc/apache2/conf-enabled/app.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app/services/${SERVICE}

FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

FROM composer:2 AS vendor
ARG SERVICE
COPY shared /app/shared
COPY services/${SERVICE} /app/services/${SERVICE}
WORKDIR /app/services/${SERVICE}
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM base AS prod
ARG SERVICE
COPY --from=vendor /app /app
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && mkdir -p /app/services/${SERVICE}/storage \
    && chown -R www-data:www-data /app/services/${SERVICE}/storage

HEALTHCHECK --interval=30s --timeout=3s CMD curl -fs http://localhost/health || exit 1