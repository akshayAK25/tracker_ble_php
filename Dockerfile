FROM php:8.2-apache

# Install MySQL extension
RUN docker-php-ext-install mysqli

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy project
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose Apache port
EXPOSE 80