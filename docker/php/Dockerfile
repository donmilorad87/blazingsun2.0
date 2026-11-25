# Same PHP line as production (Hostinger: select PHP 8.5 in hPanel).
FROM php:8.5-fpm-alpine

# pdo_mysql for the API; GD (PNG/JPEG/WebP) resizes admin uploads; the 8.5 URI extension is built in.
RUN apk add --no-cache libpng-dev libjpeg-turbo-dev libwebp-dev \
	&& docker-php-ext-configure gd --with-jpeg --with-webp \
	&& docker-php-ext-install pdo_mysql gd

# Composer, for running the PHP test suite inside the container.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer

# Development settings: show errors in the log, never in responses.
# Uploads: up to 8 MB per image (animated hero images), like the MediaStore limit.
RUN printf 'display_errors=Off\nlog_errors=On\nerror_reporting=E_ALL\nupload_max_filesize=10M\npost_max_size=12M\n' > /usr/local/etc/php/conf.d/zz-blazingsun.ini
