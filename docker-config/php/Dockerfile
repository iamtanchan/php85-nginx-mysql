FROM php:8.5-fpm-bookworm
WORKDIR /var/www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:24-bookworm-slim /usr/local/ /usr/local/

COPY . /var/www

# Install system packages and PHP extensions needed for Laravel-style apps.
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
  git \
  unzip \
  libpng-dev \
  libjpeg62-turbo-dev \
  libfreetype6-dev \
  libzip-dev \
  libpq-dev \
  curl \
  gnupg \
  openssl \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd zip \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

RUN chown -R www-data:www-data /var/www

# Add php.ini
COPY php.ini /usr/local/etc/php/
