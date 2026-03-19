FROM php:8.2-fpm

# 1. Installation des dépendances système
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    zlib1g-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Installation des extensions PHP indispensables
# intl: pour les traductions/dates, zip: pour composer, gd: pour les images
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
    intl \
    pdo \
    pdo_mysql \
    zip \
    gd \
    opcache

# 3. Installation d'APCu (le cache de Symfony)
RUN pecl install apcu && docker-php-ext-enable apcu

# 4. Installation de Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# 5. Gestion des permissions (évite les soucis de cache/logs verrouillés)
# On crée un utilisateur qui correspond souvent à l'ID 1000 sur Linux
RUN useradd -m symfony
USER symfony

WORKDIR /var/www/html