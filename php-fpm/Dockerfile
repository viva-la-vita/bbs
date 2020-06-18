FROM php:7.4-fpm-alpine

ENV LANG en_US.UTF-8
ENV LANGUAGE en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

# RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/g' /etc/apk/repositories && \
#     echo "Asia/Shanghai" > /etc/timezone

RUN apk add \
        freetype \
        freetype-dev \
        libpng \
        libpng-dev \
        oniguruma-dev \
        libjpeg-turbo \
        libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && apk del \
        freetype-dev \
        libpng-dev \
        libjpeg-turbo-dev

RUN docker-php-ext-install pdo_mysql opcache exif

RUN apk --no-cache add shadow \
    && usermod -u 1000 www-data \
    && groupmod -g 1000 www-data \
    && rm /var/cache/apk/*

ENTRYPOINT ["docker-php-entrypoint"]

STOPSIGNAL SIGQUIT

EXPOSE 9000
CMD ["php-fpm"]
