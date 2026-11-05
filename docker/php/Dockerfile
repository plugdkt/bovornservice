FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && printf "date.timezone=Asia/Bangkok\noutput_buffering=4096\nupload_max_filesize=20M\npost_max_size=20M\n" > /usr/local/etc/php/conf.d/bovorn.ini \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
