FROM php:8.5-cli
WORKDIR /usr/src/myapp

# Set memory limit to 4GB
ENV PHP_MEMORY_LIMIT=4G

RUN apt-get update && apt-get install zip unzip git curl -y \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    && mkdir /.composer && chmod 777 /.composer \
    && echo "memory_limit = 4G" > /usr/local/etc/php/conf.d/memory.ini
