# Use official PHP image with Apache
FROM php:8.2-apache

# Enable Apache mod_rewrite (common for routing)
RUN a2enmod rewrite

# Install Composer (if needed)
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy your app files into the container
COPY . /var/www/html/

# Set permissions (optional)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
