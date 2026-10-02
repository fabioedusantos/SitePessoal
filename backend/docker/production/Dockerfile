FROM php:8.5-fpm-alpine

RUN apk add --no-cache oniguruma git unzip ca-certificates libpng libjpeg-turbo libwebp freetype \
    && update-ca-certificates \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gd \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

COPY app/ ./

RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction --no-scripts \
    && chown -R www-data:www-data /var/www/html

CMD ["php-fpm"]
