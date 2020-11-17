#!/usr/bin/env sh

# Make sure all environment variables are set correctly for cron
printenv | grep -v "no_proxy" >> /etc/environment

# Start cron service
crond

# Nice way of keeping container alive while also providing logs to docker
tail -F /var/log/cron.log -F /project/storage/logs/laravel.log
