FROM dunglas/frankenphp:latest

# instalacja rozszerzeń potrzebnych dla Symfony
RUN install-php-extensions \
    pdo_mysql \
    intl \
    opcache \
    zip \
    bcmath

# katalog aplikacji
WORKDIR /app

# kopiowanie composer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/Caddyfile /etc/frankenphp/Caddyfile


# uprawnienia
RUN chown -R www-data:www-data /var

# port
EXPOSE 80

# uruchomienie
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]