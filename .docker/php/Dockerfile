# We use the version defined in the .env file
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-fpm

# Define arguments for user UID/GID to match your Linux host
ARG USER_ID
ARG GROUP_ID

# Install system dependencies and PHP extensions
# Added 'libmariadb-dev' for pdo_mysql compatibility in newer versions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libpq-dev \
    libmariadb-dev \
    git \
 && docker-php-ext-install pdo pdo_mysql zip \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Create a system user that matches your host UID/GID
# This prevents permission issues when writing files to your local disk
RUN groupadd -g ${GROUP_ID} alxarafe && \
    useradd -l -u ${USER_ID} -g alxarafe -m -s /bin/bash alxarafe

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Switch to the new user for all subsequent commands
USER alxarafe

# Expose FPM port
EXPOSE 9000