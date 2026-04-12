# DataDock — PHP + Apache. Configure MySQL/MariaDB separately (see docker-compose.example.yml).
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

# App root (adjust DocumentRoot if you mount the repo elsewhere)
WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R u+rwX /var/www/html

EXPOSE 80
