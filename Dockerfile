# Stage 1: Build the PHP environment
# Use a PHP-FPM image as the base. FPM stands for FastCGI Process Manager.
# It's a key component for running PHP with web servers like Nginx.
FROM php:8.1-fpm-alpine AS php_builder

# Install system dependencies and PHP extensions your application needs.
# 'docker-php-ext-install' is a command specific to the official PHP images.
RUN apk add --no-cache \
    git \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install \
    pdo_mysql \
    zip

# Copy your Composer files and install dependencies.
# This helps keep the final image size down and leverages Docker's build cache.
COPY composer.json composer.lock ./
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --no-scripts --no-autoloader

# Copy the rest of your PHP application code.
COPY . .

# Stage 2: Build the Nginx environment
# Use a lightweight Nginx base image.
FROM nginx:1.23-alpine AS nginx_builder

# Remove the default Nginx configuration.
RUN rm /etc/nginx/conf.d/default.conf

# Copy your custom Nginx configuration file.
# This file tells Nginx how to pass PHP requests to the PHP-FPM service.
COPY ./nginx.conf /etc/nginx/conf.d/default.conf

# Copy the PHP application files from the first stage into the Nginx container.
COPY --from=php_builder /var/www/html /var/www/html

# The default Nginx image exposes port 80.
