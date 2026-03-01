FROM php:8.4-apache AS core

RUN apt-get update \
    && apt-get install -y \
        libicu-dev \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-install \
        bcmath \
        intl \
        pdo pdo_mysql \
        zip \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && mv /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/rewrite.load

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/000-docker.ini
COPY ./docker/apache/apache2.conf /etc/apache2/apache2.conf
COPY ./docker/apache/ports.conf /etc/apache2/ports.conf
COPY ./docker/apache/app.conf /etc/apache2/sites-available/000-default.conf

###############################################################################

FROM core AS dev-tools

ENV COMPOSER_HOME=/tmp

RUN apt-get update \
    && apt-get install -y \
        git zip unzip libicu-dev libpq-dev libzip-dev \
        libonig-dev libxml2-dev curl \
    && docker-php-ext-install intl pdo pdo_mysql zip opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY ./docker/composer/php.ini /usr/local/etc/php/conf.d/custom.ini

###############################################################################

FROM dev-tools AS vendors

WORKDIR /srv/app
COPY composer.json composer.lock ./
RUN composer install \
        --no-scripts \
        --no-interaction \
        --no-ansi \
        --prefer-dist \
        --optimize-autoloader \
        --no-dev

###############################################################################

FROM core AS production

ARG APP_ENV=prod
ARG APP_USER=www-data

RUN mkdir -p /srv/app
WORKDIR /srv/app

COPY --from=vendors /srv/app/vendor vendor
COPY bin bin
COPY config config
COPY public/index.php public/index.php
COPY src src
COPY templates templates
COPY migrations migrations
COPY .env.example .env.example
COPY composer.json composer.lock ./
RUN composer install \
        --no-scripts \
        --no-interaction \
        --no-ansi \
        --prefer-dist \
        --optimize-autoloader \
        --no-dev

RUN mkdir -p /srv/app/.docker
COPY .docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY .docker/apache/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
COPY .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN chown -R ${APP_USER}:${APP_USER} /srv/app

USER ${APP_USER}
ENV APP_ENV=${APP_ENV}

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]

###############################################################################

FROM dev-tools AS development

ARG USER=www-data

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN mkdir -p /srv/app && chown $USER /srv/app
WORKDIR /srv/app
USER $USER
