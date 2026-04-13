# DataDock — PHP + Apache. Configure MySQL/MariaDB separately (see docker-compose.example.yml).
FROM php:8.5-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

# App root (adjust DocumentRoot if you mount the repo elsewhere)
WORKDIR /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY . /var/www/html/

# Fail the image build if the DB template is missing (avoids silent Hub pulls with a broken tree).
RUN test -f /var/www/html/config/db.php.example

RUN rm -f /var/www/html/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R u+rwX /var/www/html

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
