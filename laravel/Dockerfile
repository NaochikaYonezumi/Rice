FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    oniguruma-dev \
    libxml2-dev \
    imap-dev \
    openssl-dev \
    && docker-php-ext-configure imap --with-imap-ssl \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd imap

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts 2>/dev/null || true

COPY . .

RUN composer install --no-dev --optimize-autoloader 2>/dev/null || true
RUN php artisan storage:link 2>/dev/null || true

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true \
 && mkdir -p /var/lib/nginx/tmp/client_body /var/lib/nginx/tmp/proxy /var/lib/nginx/tmp/fastcgi \
 && chown -R www-data:www-data /var/lib/nginx/tmp

RUN echo "upload_max_filesize=25M" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size=25M" >> /usr/local/etc/php/conf.d/uploads.ini

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
