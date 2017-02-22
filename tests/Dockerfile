FROM composer:latest

RUN apk --no-cache add mysql-client libjpeg-turbo-dev libpng-dev freetype-dev

RUN docker-php-ext-install mysqli

RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/
RUN docker-php-ext-install gd
