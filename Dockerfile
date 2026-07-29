FROM php:8.5-fpm-trixie AS base-php
RUN apt update && apt install -y --no-install-recommends \
    curl \
    tzdata \
    && apt clean \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql
ENV TZ=Europe/Warsaw
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ >/etc/timezone

FROM node:24-alpine AS form-ui-builder
WORKDIR /app
COPY ./form-ui/package*.json .
RUN npm ci
COPY ./form-ui .
RUN npm run build

FROM node:24-alpine AS panel-ui-builder
WORKDIR /app
COPY ./panel-ui/package*.json .
RUN npm ci
COPY ./panel-ui .
RUN npm run build

FROM composer:2 AS form-deps
WORKDIR /app
COPY ./form-api/composer.json ./form-api/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts

FROM composer:2 AS panel-deps
WORKDIR /app
COPY ./panel-api/composer.json ./panel-api/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts

FROM base-php AS form-php
WORKDIR /app
COPY ./form-api ./
COPY --from=form-deps /app/vendor ./vendor

FROM base-php AS panel-php
WORKDIR /app
COPY ./panel-api ./
COPY --from=panel-deps /app/vendor ./vendor

FROM caddy:2.11-alpine AS form-web
WORKDIR /app
COPY docker/caddy/form /etc/caddy/Caddyfile
COPY --from=form-ui-builder /app/dist .
COPY --from=form-php /app/public ./public

FROM caddy:2.11-alpine AS panel-web
WORKDIR /app
COPY docker/caddy/panel /etc/caddy/Caddyfile
COPY --from=panel-ui-builder /app/dist .
COPY --from=panel-php /app/public /app/public
