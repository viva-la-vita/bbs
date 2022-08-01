FROM php:8.1-fpm-alpine AS development

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd pdo_mysql zip @composer

WORKDIR $PHP_INI_DIR

RUN mv php.ini-production php.ini && \
    sed -i 's/memory_limit = 128M/memory_limit = 256M/g' php.ini && \
    sed -i 's/post_max_size = 8M/post_max_size = 20M/g' php.ini && \
    sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 20M/g' php.ini

WORKDIR /var/www/flarum

RUN chown -R www-data:www-data .

CMD ["sh", "-c", "composer update && docker-php-entrypoint php-fpm"]

FROM development AS production

WORKDIR /var/www/flarum

COPY ["composer.json", "composer.lock", "./"]

RUN composer update

COPY [".", "."]

ENTRYPOINT ["docker-php-entrypoint"]

CMD ["php-fpm"]
