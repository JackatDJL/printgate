FROM node:22-alpine AS frontend

WORKDIR /build

COPY package.json vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm install --ignore-scripts \
    && npm run build

FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libonig-dev libsqlite3-dev unzip \
    && docker-php-ext-install mbstring opcache pdo_sqlite \
    && a2enmod headers rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && { \
        echo '<Directory /var/www/html/public>'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
    } >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

COPY --from=frontend /build/public/build ./public/build
COPY deploy/entrypoint.sh /usr/local/bin/printgate-entrypoint

RUN chmod 0755 /usr/local/bin/printgate-entrypoint \
    && mkdir -p /var/lib/printgate storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/printgate-entrypoint"]
CMD ["apache2-foreground"]
