FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        default-mysql-client \
        git \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install intl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/apps/api

COPY infra/docker/api/entrypoint.sh /usr/local/bin/humoo-api-entrypoint

RUN chmod +x /usr/local/bin/humoo-api-entrypoint

EXPOSE 8000

ENTRYPOINT ["humoo-api-entrypoint"]
