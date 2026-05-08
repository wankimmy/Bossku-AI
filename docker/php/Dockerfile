FROM php:8.3-fpm-alpine
RUN apk add --no-cache bash git curl zip unzip icu-dev oniguruma-dev libzip-dev postgresql-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql mbstring intl zip bcmath sockets \
    && pecl install redis \
    && docker-php-ext-enable redis
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
CMD ["php-fpm", "-F"]
