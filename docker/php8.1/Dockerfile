FROM php:8.1-fpm-alpine

RUN apk add --no-cache \
        libxml2-dev \
        libzip-dev \
        bash

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN docker-php-ext-enable pdo_mysql

RUN docker-php-ext-install soap

RUN docker-php-ext-install zip

RUN sh -c "wget http://getcomposer.org/composer.phar && chmod a+x composer.phar && mv composer.phar /usr/local/bin/composer"

# Algunos alias
RUN echo "PS1='\[\033[01;35m\]WsSSI\[\033[00m\]@\[\033[01;34m\]\w\[\033[00m\]:\\$ '" >> ~/.bashrc
RUN echo "alias serve='php -S 0.0.0.0:8000 -t public'" >> ~/.bashrc
RUN echo "alias ll='ls -l'" >> ~/.bashrc
RUN echo "alias s='cd ..'" >> ~/.bashrc

RUN chmod 666 /root
RUN addgroup -g 1000 1000
RUN adduser -G 1000 -u 1000 -D -h /root -s /bin/bash tic

# ARG UNAME=atorres
# ARG UID=1000
# ARG GID=1000
# RUN groupadd -g $GID -o $UNAME
# RUN useradd -m -u $UID -g $GID -o -s /bin/bash $UNAME

# USER $UNAME