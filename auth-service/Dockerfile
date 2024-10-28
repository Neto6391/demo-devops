FROM php:8.2.25-fpm-alpine

# Instalar dependências e extensões
RUN apk --no-cache add \
    bash \
    autoconf \
    g++ \
    make \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install opcache gd

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configurar o diretório de trabalho
WORKDIR /var/www/html/auth-service

# Copiar arquivos do projeto
COPY . .

# Instalar dependências do Composer
RUN composer install --no-scripts --prefer-dist --no-interaction --no-plugins

# Configurar PHP-FPM
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php.ini /usr/local/etc/php/php.ini

EXPOSE 9000

CMD ["php-fpm"]
