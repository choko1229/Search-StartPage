FROM php:8.3-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends systemd && rm -rf /var/lib/apt/lists/*
# Match the distribution PHP path used by the shipped example without starting a manager.
RUN ln -s /usr/local/bin/php /usr/bin/php
