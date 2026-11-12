FROM php:8.4-cli

RUN docker-php-ext-install pdo_mysql
WORKDIR /app
COPY public public
COPY src src
COPY views views
COPY scripts scripts
COPY database database
