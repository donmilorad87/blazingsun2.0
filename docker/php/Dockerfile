# Same PHP line as production (Hostinger: select PHP 8.5 in hPanel).
FROM php:8.5-fpm-alpine

# pdo_mysql for the API; the 8.5 URI extension is built in.
RUN docker-php-ext-install pdo_mysql

# Composer, for running the PHP test suite inside the container.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer

# Development settings: show errors in the log, never in responses.
RUN printf 'display_errors=Off\nlog_errors=On\nerror_reporting=E_ALL\n' > /usr/local/etc/php/conf.d/zz-blazingsun.ini
