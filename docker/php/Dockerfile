FROM php:8.3-fpm-bookworm

ARG COMPOSER_VERSION=2.9.3

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        curl \
        git \
        gnupg \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libssl-dev \
        libwebp-dev \
        libxml2-dev \
        libxslt1-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        ftp \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo_mysql \
        soap \
        sockets \
        xsl \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -fsSL "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" -o /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/zz-magento.ini
COPY opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

ENV COMPOSER_HOME=/var/www/.composer
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

RUN usermod -u 1000 www-data \
    && groupmod -g 1000 www-data \
    && mkdir -p /var/www/.composer /tmp/composer-cache /var/www/html \
    && chown -R www-data:www-data /var/www/.composer /tmp/composer-cache /var/www/html
