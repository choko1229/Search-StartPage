FROM php:8.3-fpm-bookworm
RUN docker-php-ext-install pdo_mysql && apt-get update && apt-get install -y --no-install-recommends nginx && rm -rf /var/lib/apt/lists/*
RUN printf 'opcache.enable=1\nopcache.validate_timestamps=0\nopcache.file_update_protection=0\ndisplay_errors=0\n' > /usr/local/etc/php/conf.d/update-test.ini
RUN printf '[www]\npm=static\npm.max_children=2\n' > /usr/local/etc/php-fpm.d/zz-update-test.conf
