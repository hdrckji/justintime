FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker/apache-entrypoint.sh /usr/local/bin/apache-entrypoint.sh
RUN chmod +x /usr/local/bin/apache-entrypoint.sh

EXPOSE 80

CMD ["apache-entrypoint.sh"]
