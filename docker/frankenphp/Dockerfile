FROM dunglas/frankenphp:1-php8.4-alpine AS base

WORKDIR /app

# ===============================
# FIX RESEAU + SSL
# ===============================
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.ocf.berkeley.edu/g' /etc/apk/repositories \
    && apk add --no-cache ca-certificates \
    && update-ca-certificates

# ===============================
# DEPENDANCES SYSTEME
# ===============================
RUN apk add --no-cache \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    zlib-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    pkgconf \
    git \
    unzip \
    bash \
    wget \
    autoconf \
    g++ \
    make \
    postgresql-dev \
    imagemagick \
    imagemagick-dev \
    ghostscript \
    poppler-utils \
    libreoffice \
    ttf-freefont \
    libc6-compat

# ===============================
# PHP EXTENSIONS
# ===============================
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        intl \
        opcache \
        pdo_pgsql \
        zip

# ===============================
# PECL
# ===============================
RUN pecl install imagick redis \
    && docker-php-ext-enable imagick redis

# ===============================
# COMPOSER
# ===============================
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# ===============================
# SYMFONY CLI
# ===============================
RUN wget https://github.com/symfony-cli/symfony-cli/releases/latest/download/symfony-cli_linux_amd64.tar.gz \
    && tar -xzf symfony-cli_linux_amd64.tar.gz -C /usr/local/bin \
    && rm symfony-cli_linux_amd64.tar.gz \
    && chmod +x /usr/local/bin/symfony

# ===============================
# CONFIG PHP
# ===============================
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# ===============================
# CADDY / FRANKENPHP
# ===============================
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile


FROM base AS dev
ENV APP_ENV=dev
EXPOSE 8080


CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
