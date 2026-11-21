FROM php:8.3-cli

# intl isn't in the base image; pdo_sqlite and mbstring already are
RUN apt-get update && apt-get install -y libicu-dev \
    && docker-php-ext-install intl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .

# Render sets $PORT. Seed the DB on boot, then start the server.
CMD ["sh", "-c", "php bin/seed.php && php -S 0.0.0.0:${PORT:-10000} -t public"]
