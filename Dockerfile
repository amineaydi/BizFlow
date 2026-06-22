FROM php:8.2-apache

# Install extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN docker-php-ext-enable mysqli pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy code
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Port
EXPOSE 80
