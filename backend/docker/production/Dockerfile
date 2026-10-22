FROM php:8.5.12-fpm-alpine

RUN apk add --no-cache oniguruma git unzip ca-certificates libpng libjpeg-turbo libwebp freetype \
    && update-ca-certificates \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gd \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN printf '%s\n' \
      'expose_php=Off' \
      'display_errors=Off' \
      'log_errors=On' \
      'cgi.fix_pathinfo=0' \
      'memory_limit=256M' \
      'upload_max_filesize=12M' \
      'post_max_size=14M' \
      'max_file_uploads=10' \
      'session.use_strict_mode=1' \
      'opcache.enable=1' \
      'opcache.validate_timestamps=0' \
      'opcache.memory_consumption=128' \
      > /usr/local/etc/php/conf.d/production-security.ini \
    && printf '%s\n' \
      '[www]' \
      'php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen' \
      > /usr/local/etc/php-fpm.d/zz-security.conf

WORKDIR /var/www/html

COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

COPY app/ ./

RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction --no-scripts \
    && chown -R www-data:www-data /var/www/html

CMD ["php-fpm"]
