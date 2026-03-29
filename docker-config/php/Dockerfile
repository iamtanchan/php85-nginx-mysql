FROM php:8.5-fpm-bookworm
WORKDIR /var/www/src

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
  git \
  unzip \
  libpng-dev \
  libjpeg62-turbo-dev \
  libfreetype6-dev \
  libzip-dev \
  curl \
  gnupg \
  openssl \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd zip \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

COPY docker-config/php/php.ini /usr/local/etc/php/php.ini
COPY src /var/www/src
COPY ca-certificate.crt /var/www/ca-certificate.crt

RUN mkdir -p /var/www/src/uploads /var/www/src/data \
  && chown -R www-data:www-data /var/www
