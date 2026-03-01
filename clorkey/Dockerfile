# =============================================================================
# Stage: base — PHP 8.5 + extensões + Composer
# =============================================================================
FROM ubuntu:24.04 AS base

ARG DEBIAN_FRONTEND=noninteractive
ARG TZ=UTC
ENV TZ=${TZ}

RUN ln -snf /usr/share/zoneinfo/${TZ} /etc/localtime && echo ${TZ} > /etc/timezone \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
        software-properties-common \
        unzip \
        zip \
        git \
        supervisor \
        ghostscript \
    && add-apt-repository ppa:ondrej/php \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        php8.5-cli \
        php8.5-fpm \
        php8.5-common \
        php8.5-pgsql \
        php8.5-mysql \
        php8.5-sqlite3 \
        php8.5-gd \
        php8.5-zip \
        php8.5-bcmath \
        php8.5-redis \
        php8.5-intl \
        php8.5-exif \
        php8.5-imagick \
        php8.5-soap \
        php8.5-xml \
        php8.5-curl \
        php8.5-mbstring \
        php8.5-tokenizer \
        php8.5-fileinfo \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# =============================================================================
# Stage: node-deps — instala dependências npm
# =============================================================================
FROM node:22-alpine AS node-deps

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# =============================================================================
# Stage: builder — builda assets Vite para produção
# =============================================================================
FROM node-deps AS builder

COPY . .
RUN npm run build

# =============================================================================
# Stage: composer-deps — instala dependências PHP de produção
# (layer cacheável: só invalida quando composer.json/lock mudam)
# =============================================================================
FROM base AS composer-deps

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# =============================================================================
# Stage: dev — desenvolvimento com php artisan serve + Vite HMR
# =============================================================================
FROM base AS dev

ARG WWWUSER=1000
ARG WWWGROUP=1000

# Instala Node 22 para rodar Vite dev server
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instala xdebug
RUN apt-get update \
    && apt-get install -y --no-install-recommends php8.5-xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Cria usuário sail compatível com o uid/gid do host
# Ubuntu 24.04 pode ter um user com UID 1000 (ubuntu), remove antes de criar sail
RUN userdel -r $(id -un ${WWWUSER} 2>/dev/null) 2>/dev/null || true \
    && groupadd --force --gid ${WWWGROUP} sail \
    && useradd --uid ${WWWUSER} --gid ${WWWGROUP} --create-home --shell /bin/bash sail

# Copia composer deps primeiro (mais cache-friendly)
COPY composer.json composer.lock ./
RUN mkdir -p bootstrap/cache storage/framework/{cache,sessions,views,testing} storage/logs \
    && composer install --no-interaction --prefer-dist --no-scripts

COPY --from=node-deps /app/node_modules ./node_modules

# Copia código da aplicação (será sobrescrito pelo bind mount em dev)
COPY . .

# Roda scripts do composer agora que os arquivos da app estão disponíveis
RUN composer run-script post-autoload-dump || true

# Copia config do supervisord para gerenciar php serve + vite
COPY .docker/supervisord-dev.conf /etc/supervisor/conf.d/app.conf

RUN mkdir -p /var/log/supervisor \
    && chown -R sail:sail /var/www/html \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000 5173

# Supervisord roda como root para gerenciar os processos filhos como 'sail'
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]

# =============================================================================
# Stage: prod — PHP-FPM otimizado para produção
# =============================================================================
FROM base AS prod

# Copia vendor otimizado do stage composer-deps
COPY --from=composer-deps --chown=www-data:www-data /var/www/html/vendor ./vendor

# Copia código da aplicação
COPY --chown=www-data:www-data . .

# Copia assets buildados pelo stage builder
COPY --from=builder --chown=www-data:www-data /app/public/build ./public/build

# Roda scripts do Composer (post-autoload-dump, etc.)
RUN composer run-script post-autoload-dump || true

# Configura permissões dos diretórios de cache/storage
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views,testing} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Configura PHP-FPM para ouvir em TCP (necessário para nginx acessar via rede)
RUN sed -i \
    's|listen = /run/php/php8.5-fpm.sock|listen = 0.0.0.0:9000|g' \
    /etc/php/8.5/fpm/pool.d/www.conf \
    && sed -i \
    's|;daemonize = yes|daemonize = no|g' \
    /etc/php/8.5/fpm/php-fpm.conf \
    && sed -i \
    's|error_log = /var/log/php8.5-fpm.log|error_log = /proc/self/fd/2|g' \
    /etc/php/8.5/fpm/php-fpm.conf

USER www-data
EXPOSE 9000

CMD ["/usr/sbin/php-fpm8.5", "-F"]
