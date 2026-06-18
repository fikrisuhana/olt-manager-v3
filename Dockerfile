FROM php:8.3-apache

# Apache: aktifkan mod_rewrite + headers (wajib untuk CI4 routing)
RUN a2enmod rewrite headers

# Copy vhost config
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Install system deps + PHP extensions
RUN apt-get update -y && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        zip \
        opcache \
        bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP runtime config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/gpon.ini

# Composer (dari image resmi, tidak install lewat curl)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies dulu (layer cache — lebih cepat rebuild kalau kode saja yang berubah)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# Copy seluruh source code
COPY . .

# Dump autoloader sekali setelah semua kode ada
RUN composer dump-autoload --optimize --classmap-authoritative

# Buat direktori writable dan set permission
RUN mkdir -p writable/{cache,logs,session,uploads,onu_cache,debugbar} \
    && chown -R www-data:www-data writable \
    && chmod -R 755 writable

# Entrypoint: generate .env dari env vars kalau belum ada
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
