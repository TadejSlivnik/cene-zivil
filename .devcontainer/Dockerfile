# See https://containers.dev/ for full reference
# Sample Dockerfile for PHP/Symfony development
FROM mcr.microsoft.com/devcontainers/php:7.4

# Uninstall Xdebug if present
RUN pecl uninstall xdebug || true

# Remove broken Yarn repo if present, then install system dependencies
RUN rm -f /etc/apt/sources.list.d/yarn.list /etc/apt/trusted.gpg.d/yarn.gpg || true \
    && apt-get update \
    && apt-get install -y git unzip libicu-dev libpq-dev libzip-dev zlib1g-dev \
    && docker-php-ext-install intl pdo pdo_mysql zip



# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer


# Install Node.js (for Encore/Webpack)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs


# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony


# Set working directory
WORKDIR /workspace
