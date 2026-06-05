FROM php:8.3-fpm-alpine
# nodejs+npm let the executor run JS package-manager project commands (npm/pnpm/yarn/npx)
# in the writable /workspace mount; corepack provisions pnpm/yarn (honouring a project's
# packageManager field) with a global pnpm install as a fallback.
RUN apk add --no-cache bash git curl zip unzip sudo docker-cli docker-cli-compose nodejs npm icu-dev oniguruma-dev libzip-dev postgresql-dev postgresql18-client linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql mbstring intl zip bcmath sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && npm install -g pnpm yarn \
    && (corepack enable || true)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY entrypoint.sh /tmp/bossku-entrypoint.in
COPY bossku-chmod-project.sh /tmp/bossku-chmod-project.in
# Strip CRLF when the repo was checked out on Windows (fixes "can't execute sh^M")
RUN sed 's/\r$//' /tmp/bossku-entrypoint.in > /usr/local/bin/bossku-entrypoint \
    && chmod +x /usr/local/bin/bossku-entrypoint \
    && rm -f /tmp/bossku-entrypoint.in \
    && sed 's/\r$//' /tmp/bossku-chmod-project.in > /usr/local/bin/bossku-chmod-project \
    && chmod +x /usr/local/bin/bossku-chmod-project \
    && rm -f /tmp/bossku-chmod-project.in \
    && echo 'www-data ALL=(root) NOPASSWD: /usr/local/bin/bossku-chmod-project' > /etc/sudoers.d/bossku-www-data \
    && chmod 440 /etc/sudoers.d/bossku-www-data
WORKDIR /var/www/html
ENTRYPOINT ["/usr/local/bin/bossku-entrypoint"]
CMD ["php-fpm", "-F"]
