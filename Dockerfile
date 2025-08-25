FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    bash \
    nodejs \
    npm \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath gd opcache mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . /var/www

# Копируем Laravel composer-файлы и ставим зависимости
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist || true

CMD ["php", "artisan", "bot:polling"]
# CMD ["php", "artisan", "schedule:work"]

