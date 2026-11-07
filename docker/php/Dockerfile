# ============================================================================
# Stage 1: base — общие зависимости (используется и dev, и prod)
# ============================================================================
FROM php:8.3-fpm-alpine AS base

# --- Системные зависимости ---
# icu-dev      — intl (Symfony Translator, NumberFormatter)
# libzip-dev   — zip (Composer)
# oniguruma    — mbstring
# postgresql   — pdo_pgsql (на случай, если БД переедет)
RUN apk add --no-cache \
        acl \
        fcgi \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        rabbitmq-c-dev \
        git \
        unzip \
        bash \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        pdo_pgsql \
        zip \
        bcmath \
        sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# --- Composer ---
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# --- PHP-конфиг ---
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

# --- Непривилегированный пользователь (совпадает с UID хоста) ---
ARG UID=1000
ARG GID=1000
RUN addgroup -g "${GID}" -S app 2>/dev/null || true \
    && adduser  -u "${UID}" -S app -G app 2>/dev/null || true

WORKDIR /var/www/html

# ============================================================================
# Stage 2: dev — с Xdebug, без оптимизации
# ============================================================================
FROM base AS dev

RUN apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .xdebug-deps

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/98-xdebug.ini

# В dev не оптимизируем opcache — правки видны сразу
RUN echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/99-app.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/99-app.ini

USER app

CMD ["php-fpm"]

# ============================================================================
# Stage 3: prod — без Xdebug, с preload-скриптом и строгим opcache
# ============================================================================
FROM base AS prod

# Preload (опционально): Symfony preload.php из runtime
# COPY --from=... /var/www/html/var/cache/prod/App_KernelProdContainer.preload.php ...

USER app

CMD ["php-fpm"]