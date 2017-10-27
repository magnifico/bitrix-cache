FROM php:7.0-cli

RUN apt-get update &&\
    apt-get install -y libzip-dev &&\
    docker-php-ext-install zip

RUN pecl channel-update pecl.php.net &&\
    pecl install redis-3.1.0 xdebug &&\
    docker-php-ext-enable redis xdebug

RUN curl -sLO https://getcomposer.org/download/1.5.2/composer.phar &&\
    chmod +x composer.phar &&\
    mv composer.phar /usr/bin/composer

COPY . /app

WORKDIR /app

RUN /usr/bin/composer install

CMD [ "/app/vendor/bin/phpunit" ]
