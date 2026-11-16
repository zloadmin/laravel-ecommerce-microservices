# Shared PHP image for every Laravel service.
# Build context is the repository root so services can reach packages/shared.
FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions \
        pdo_pgsql \
        redis \
        sockets \
        pcntl \
        bcmath \
        intl \
        zip \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

# Development target: source code is bind-mounted at /workspace by Compose,
# mirroring the repository layout so the path repository to packages/shared resolves.
FROM base AS dev

WORKDIR /workspace

EXPOSE 8080

CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "public"]
