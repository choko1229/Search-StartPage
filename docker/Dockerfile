FROM php:8.2-apache-bookworm
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite headers
WORKDIR /var/www/app
COPY . /var/www/app
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/search.ini
RUN mkdir -p storage/logs storage/cache storage/uploads/backgrounds storage/updates storage/backups \
    && chown -R www-data:www-data config storage \
    && chmod 700 config storage
