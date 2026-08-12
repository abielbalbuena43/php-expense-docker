FROM php:8.0-apache

# Install database drivers and system tools needed for Composer zip extraction
RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && docker-php-ext-enable mysqli pdo_mysql zip

# Enable URL rewriting for custom paths (.htaccess compatibility)
RUN a2enmod rewrite

# Install Composer securely inside the container image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Point working directory to the container web root
WORKDIR /var/www/html

# Run composer install automatically if a composer.json file exists
# This makes sure all your vendor folder dependencies are fully active
RUN if [ -f "composer.json" ]; then composer install --no-interaction --optimize-autoloader; fi
