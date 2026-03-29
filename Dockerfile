FROM php:8.5-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/src

WORKDIR /var/www/html

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
  && a2enmod rewrite \
  && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
  && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
  && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

COPY docker-config/php/php.ini /usr/local/etc/php/php.ini
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
