FROM php:8.0-apache

# Install database drivers and system tools needed by the application
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && docker-php-ext-enable mysqli pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache web root
WORKDIR /var/www/html

# Copy the PHP application into the Docker image
COPY src/ /var/www/html/

# Install Composer dependencies if composer.json exists
RUN if [ -f "composer.json" ]; then \
        composer install --no-interaction --prefer-dist --optimize-autoloader; \
    fi

# Keep Apache on port 80 inside the container.
# Render can map its external PORT to this container port.
EXPOSE 80
