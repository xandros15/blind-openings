FROM node:24-alpine AS frontend-builder
WORKDIR /app
COPY ./list-request/ui/package*.json .
RUN npm ci
COPY ./list-request/ui .
RUN npm run build

FROM node:24-alpine AS panel-ui-builder
WORKDIR /app
COPY ./panel/package*.json .
RUN npm ci
COPY ./panel .
RUN npm run build

FROM composer:2 AS deps
WORKDIR /app
COPY list-request/api/composer.json list-request/api/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts

FROM composer:2 AS panel-deps
WORKDIR /app
COPY themes/composer.json themes/composer.lock ./
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

FROM php:8.5-fpm-trixie AS panel-php
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
COPY themes ./
COPY --from=panel-deps /app/vendor ./vendor

FROM caddy:2.11-alpine AS web
WORKDIR /app
COPY docker/web /etc/caddy/Caddyfile
COPY --from=frontend-builder /app/dist .
COPY --from=php /app/public ./public

FROM caddy:2.11-alpine AS panel-web
WORKDIR /app
COPY docker/panel /etc/caddy/Caddyfile
COPY --from=panel-ui-builder /app/dist .
COPY --from=panel-php /app/public /app/public
