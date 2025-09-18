# Use a PHP-FPM image with a compatible version (e.g., 8.2-fpm-alpine)
FROM php:8.2-fpm-alpine AS php_builder

# Install system dependencies and required PHP extensions.
# Note the addition of 'gd' and its dependencies ('libpng-dev' and 'freetype-dev').
RUN apk add --no-cache \
    git \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    freetype-dev \
    jpeg-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    zip \
    gd \
    && rm -rf /var/cache/apk/*

# Install Composer.
COPY --from=composer/composer:2-bin /composer /usr/bin/composer

# Set the working directory for the application.
WORKDIR /var/www/html

# Copy your application files.
COPY . .

# Install PHP dependencies from composer.lock.
RUN composer install --no-dev --no-scripts --no-autoloader

# Stage 2: Build the Nginx environment
FROM nginx:1.23-alpine

# Remove the default Nginx configuration.
RUN rm /etc/nginx/conf.d/default.conf

# Copy your custom Nginx configuration file.
COPY ./nginx.conf /etc/nginx/conf.d/default.conf

# Copy the PHP application files from the first stage into the Nginx container.
COPY --from=php_builder /var/www/html /var/www/html
