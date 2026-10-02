# Isolated optional-codec verification image; the app also works without these codecs.
ARG BASE_IMAGE=php:8.2-apache-bookworm
FROM ${BASE_IMAGE}
RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg libpng-dev libjpeg62-turbo-dev libwebp-dev libmagickwand-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd exif \
    && pecl install imagick-3.8.1 && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/app
COPY app /var/www/app/app
COPY tests /var/www/app/tests
