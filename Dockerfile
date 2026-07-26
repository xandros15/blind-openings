FROM node:24-alpine AS frontend-builder
WORKDIR /app
COPY ./list-request/ui/package*.json .
RUN npm ci
COPY ./list-request/ui .
RUN npm run build

FROM composer:2 AS deps
WORKDIR /app
COPY list-request/api/composer.json list-request/api/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts

FROM php:8.5-fpm-trixie AS php
RUN apt update && apt install -y --no-install-recommends \
    curl \
    tzdata \
    && apt clean \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql
ENV TZ=Europe/Warsaw
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ >/etc/timezone
WORKDIR /app
COPY list-request/api ./
COPY --from=deps /app/vendor ./vendor

FROM caddy:2.11-alpine AS web
WORKDIR /app
COPY docker/web /etc/caddy/Caddyfile
COPY --from=frontend-builder /app/dist .
COPY --from=php /app/public ./public
