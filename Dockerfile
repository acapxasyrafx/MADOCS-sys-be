FROM php:8.1-apache

# Set the working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    lua-zlib-dev \
    libmemcached-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo_mysql mbstring zip exif pcntl \
    && rm -rf /var/lib/apt/lists/*

# Copy composer files and install dependencies
COPY composer.lock composer.json ./
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy the rest of the application
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Add a non-root user
RUN useradd -G www-data,root -u 1000 -d /home/devuser devuser \
    && mkdir -p /home/devuser/.composer \
    && chown -R devuser:devuser /home/devuser

USER devuser

# Clear Laravel configuration cache
RUN php artisan config:clear

# Start Apache
CMD ["apache2-foreground"]
