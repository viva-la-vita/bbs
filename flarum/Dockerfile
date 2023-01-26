FROM php:8.1-fpm-alpine AS development

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions exif gd gmp pdo_mysql zip @composer

WORKDIR $PHP_INI_DIR

RUN mv php.ini-production php.ini

WORKDIR /var/www/flarum

RUN chown -R www-data:www-data .

CMD ["sh", "-c", "composer update && docker-php-entrypoint php-fpm"]

FROM development AS production

COPY php/flarum.ini /usr/local/etc/php/conf.d/

COPY php/zzz-flarum.conf /usr/local/etc/php-fpm.d/

WORKDIR /var/www/flarum

COPY www/composer.json www/composer.lock ./

RUN composer update

COPY www .

ENTRYPOINT ["docker-php-entrypoint"]

CMD ["php-fpm"]
