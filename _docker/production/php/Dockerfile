# Use the official PHP 8.2 FPM Alpine base image
FROM php:8.2-fpm-alpine AS builder

# Install dependencies and PHP extensions
RUN set -ex \
    # Install build dependencies
    && apk add --no-cache --virtual .build-deps \
        libtool \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        postgresql-dev \
        zlib-dev \
    \
    # Install runtime dependencies
    && apk add --no-cache \
        libzip \
        freetype \
        libjpeg-turbo \
        libpng \
        zlib

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
    docker-php-ext-install pgsql pdo_pgsql && \
    docker-php-ext-configure intl && \
    docker-php-ext-install -j$(nproc) intl && \
    docker-php-ext-install zip && \
    docker-php-ext-install gd

# Stage 2: Runtime Stage
FROM php:8.2-fpm-alpine

# Copy compiled PHP extensions from the build stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=composer:2.4.4 /usr/bin/composer /usr/local/bin/composer

# Install only runtime dependencies
RUN apk add --no-cache \
        icu \
        libzip \
        freetype \
        libjpeg-turbo \
        libpng \
        zlib \
        libpq

ARG user
ARG uid
# Create system user to run Composer and Artisan Commands

# Create group rvdev014 in root and www-data group and add user rvdev014 to it
RUN addgroup -S $user && \
    adduser -S -u $uid -G $user -G www-data -G root -h /home/$user $user

RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

USER $user

# Set working directory
WORKDIR /app

COPY ./composer.* .
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY ./_docker/production/php/php.ini /usr/local/etc/php/conf.d/php.ini

COPY --chown=$user:$user . .
RUN composer dump-autoload --optimize

# Expose default PHP-FPM port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
