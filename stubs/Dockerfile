# -------------------------------------------------------------------------------------------------- #
FROM alpine:3.12.0 AS foundation

# Install PHP along side needed extensions
RUN apk add --update \
    php \
    php-dom \
    php-fileinfo \
    php-json \
    php-opcache \
    php-openssl \
    php-pdo \
    php-pdo_mysql \
    php-phar \
    php-session \
    php-tokenizer \
    php-xml \
    php-xmlwriter \
    php-curl \
    php-mbstring \
    php7-redis



# -------------------------------------------------------------------------------------------------- #
FROM foundation AS project

# Copy project files to the container and set workdir
COPY ./project /project
WORKDIR /project



# -------------------------------------------------------------------------------------------------- #
FROM project AS dependencies

# Install and update composer
RUN apk add --update composer && composer self-update

# Install composer dependencies
RUN composer install



# -------------------------------------------------------------------------------------------------- #
FROM project AS base

COPY --from=dependencies /project/vendor /project/vendor

# Publish vendor packages that are needed (These are ignored if composer package is missing)
RUN php artisan vendor:publish --provider='Cego\RequestLog\RequestLogServiceProvider'
RUN php artisan vendor:publish --provider='Cego\RequestInsurance\RequestInsuranceServiceProvider'



# -------------------------------------------------------------------------------------------------- #
FROM base AS api

# Install nginx and php-fpm
RUN apk add --update php-fpm nginx

# Copy needed files and configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm.conf /etc/php7/php-fpm.conf
COPY docker/php.ini /etc/php7/conf.d/overrides.ini
COPY docker/entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]



# -------------------------------------------------------------------------------------------------- #
FROM base AS cron

# Copy needed files and configuration
COPY docker/php.ini /etc/php7/conf.d/overrides.ini
COPY docker/crontab /etc/crontabs/root
COPY docker/entrypoint-cron.sh /entrypoint-cron.sh

# Ensure permissions and log files is in place
RUN chmod 0644 /etc/crontabs/root
RUN touch /var/log/cron.log

ENTRYPOINT ["/entrypoint-cron.sh"]



# -------------------------------------------------------------------------------------------------- #
FROM base AS migrator

CMD ["php", "artisan", "migrate", "--force"]



# -------------------------------------------------------------------------------------------------- #
FROM base AS seeder

CMD ["php", "artisan", "db:seed", "--force"]



# -------------------------------------------------------------------------------------------------- #
FROM base AS shell

# Install and update composer and install npm
RUN apk add --update composer npm vim && composer self-update

CMD ["sh"]



# -------------------------------------------------------------------------------------------------- #
FROM base AS phpunit

# Install XDebug for code coverage
RUN apk add --update php7-xdebug php-pdo_sqlite

# Run tests
RUN ./vendor/bin/phpunit --coverage-text



# -------------------------------------------------------------------------------------------------- #
FROM base AS phpcsfixer

# Run linter
RUN ./vendor/bin/php-cs-fixer fix --dry-run --verbose



# -------------------------------------------------------------------------------------------------- #
FROM base AS phpstan

# Run code analysis
RUN ./vendor/bin/phpstan analyse -l 7 --memory-limit=2G -c phpstan.neon app
