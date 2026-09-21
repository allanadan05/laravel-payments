FROM php:8.2-cli

# git + unzip are needed by Composer; libzip-dev is needed to build the zip
# extension, libonig-dev (oniguruma) is needed to build mbstring.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install zip mbstring \
    && rm -rf /var/lib/apt/lists/*

# Grab the official Composer binary instead of installing it via script.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["bash"]
