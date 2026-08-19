FROM php:8.4-apache

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

ENV APP_HOME "/var/www/html"

RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

RUN a2enmod rewrite
RUN a2enmod ssl
RUN a2enmod headers

ENV APACHE_DOCUMENT_ROOT="/var/www/html/public"
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN apt-get update  \
    && apt-get install -y \
        git \
        libzip-dev \
        zip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/* \
    && git config --global --add safe.directory '*'

# Install Xdebug for code coverage
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=no" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

ENV XDEBUG_MODE=coverage

RUN a2enmod rewrite headers

COPY . $APP_HOME

RUN composer install --no-interaction

RUN chown -R www-data:www-data $APP_HOME

CMD ["sh", "-c", "git config --global --add safe.directory /var/www/html || true && composer install --no-interaction && apache2-foreground"]
