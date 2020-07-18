FROM php:7.1-apache

ENV PROJECT_DIR=/var/www/html \
    APP_URL=localhost

RUN docker-php-ext-install mysqli gettext

RUN pecl install xdebug \
	&& docker-php-ext-enable xdebug

COPY xdebug.ini /usr/local/etc/php/conf.d/
	
COPY ./src $PROJECT_DIR
COPY docker-entrypoint.sh /entrypoint.sh

RUN sed -i 's/\r//' /entrypoint.sh

VOLUME $PROJECT_DIR/storage

ENTRYPOINT ["/bin/bash", "/entrypoint.sh"]
CMD ["run"]
