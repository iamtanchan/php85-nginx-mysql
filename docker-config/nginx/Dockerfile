FROM nginx:1.28-alpine
WORKDIR /var/www

COPY docker-config/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY src /var/www/src
