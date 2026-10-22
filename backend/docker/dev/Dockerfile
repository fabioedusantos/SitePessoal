FROM php:8.5.12-fpm-alpine

RUN apk add --no-cache oniguruma git unzip ca-certificates libpng libjpeg-turbo libwebp freetype \
    && update-ca-certificates \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gd \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN printf 'upload_max_filesize=12M\npost_max_size=14M\nmax_file_uploads=10\n' > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

CMD ["php-fpm"]
