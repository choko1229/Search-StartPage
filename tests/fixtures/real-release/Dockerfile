ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev && docker-php-ext-install pdo_mysql curl && rm -rf /var/lib/apt/lists/*
RUN printf 'display_errors=0\n' > /usr/local/etc/php/conf.d/release-test.ini
