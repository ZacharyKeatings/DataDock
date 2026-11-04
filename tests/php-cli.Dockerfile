# PHP CLI with pdo_mysql for machines whose system PHP lacks the extension.
# GitHub Actions installs php-mysql instead of building this image.
FROM php:8.3-cli
RUN docker-php-ext-install pdo_mysql
