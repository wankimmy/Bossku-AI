FROM php:8.3-fpm-alpine
RUN apk add --no-cache bash git curl zip unzip icu-dev oniguruma-dev libzip-dev postgresql-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql mbstring intl zip bcmath sockets \
    && pecl install redis \
    && docker-php-ext-enable redis
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY entrypoint.sh /tmp/bossku-entrypoint.in
# Strip CRLF when the repo was checked out on Windows (fixes "can't execute sh^M")
RUN sed 's/\r$//' /tmp/bossku-entrypoint.in > /usr/local/bin/bossku-entrypoint \
    && chmod +x /usr/local/bin/bossku-entrypoint \
    && rm -f /tmp/bossku-entrypoint.in
WORKDIR /var/www/html
ENTRYPOINT ["/usr/local/bin/bossku-entrypoint"]
CMD ["php-fpm", "-F"]
