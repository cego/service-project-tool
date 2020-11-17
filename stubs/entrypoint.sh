#!/usr/bin/env sh

# Start php-fpm and nginx services
php-fpm7 -R
nginx

# Nice way of keeping container alive while also providing logs to docker
tail -F /var/log/php7/error.log -F /project/storage/logs/laravel.log
