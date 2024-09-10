# Use official PHP 8.1 image with Apache
FROM php:8.1-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mbstring zip exif pcntl

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy existing application files
COPY . .

# Copy the .env file (assuming you have a .env.development in your project directory)
COPY .env.development /var/www/html/.env

# Set proper permissions for storage and cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Run Composer to install dependencies with memory limit to avoid OOM issues
RUN COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Set proper permissions again for storage and cache after composer install
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Create a non-root user and set permissions
RUN useradd -G www-data,root -u 1000 -d /home/devuser devuser
RUN mkdir -p /home/devuser/.composer && \
    chown -R devuser:devuser /home/devuser

# Switch back to the 'www-data' user to run artisan commands and avoid permission issues
USER www-data

# Run artisan commands to clear cache and prepare the app for use
RUN php artisan config:clear

# Expose port 80 for Apache
EXPOSE 80

# Start Apache in the foreground with the Laravel application
CMD ["apache2-foreground"]
