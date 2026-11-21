FROM php:8.3-cli-bookworm

ARG TAILWIND_VERSION=v4.1.4
ARG ESBUILD_VERSION=0.25.0

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip curl ca-certificates libicu-dev libzip-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node

COPY install-tools.sh /tmp/install-tools.sh
RUN sh /tmp/install-tools.sh "$TAILWIND_VERSION" "$ESBUILD_VERSION" /usr/local/bin && rm /tmp/install-tools.sh

COPY php/dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

ENV HOME=/tmp \
    COMPOSER_HOME=/tmp/composer \
    TAILWIND_BIN=/usr/local/bin/tailwindcss \
    ESBUILD_BIN=/usr/local/bin/esbuild

WORKDIR /app
EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]