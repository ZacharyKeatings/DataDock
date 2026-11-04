# DataDock — PHP + Apache. Configure MySQL/MariaDB separately (see docker-compose.example.yml).
FROM php:8.5-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && printf '%s\n' \
        '<Directory /var/www/html/uploads>' \
        '    Require all denied' \
        '</Directory>' \
        '<Directory /var/www/html/thumbnails>' \
        '    Require all denied' \
        '</Directory>' \
        > /etc/apache2/conf-available/datadock-deny-storage.conf \
    && a2enconf datadock-deny-storage \
    && printf '%s\n' \
        'Timeout 300' \
        'LimitRequestBody 0' \
        > /etc/apache2/conf-available/datadock-upload.conf \
    && a2enconf datadock-upload \
    && printf '%s\n' \
        'file_uploads=On' \
        'upload_max_filesize=512M' \
        'post_max_size=512M' \
        'memory_limit=256M' \
        'max_execution_time=300' \
        'max_input_time=300' \
        'max_file_uploads=50' \
        'display_errors=Off' \
        'log_errors=On' \
        > /usr/local/etc/php/conf.d/datadock.ini

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

# Site settings and uploaded files. Mount a named volume here so compose down/up
# and --force-recreate keep the install. install.php is removed after setup.
# DATADOCK_IMAGE_MODE=0 (or a bind-mounted .git checkout) leaves install.php in place.
ENV DATADOCK_DATA_DIR=/var/lib/datadock
ENV DATADOCK_IMAGE_MODE=1
VOLUME /var/lib/datadock

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
