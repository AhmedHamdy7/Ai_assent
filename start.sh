#!/bin/bash
set -e

php artisan config:cache
php artisan migrate --force

# Run reminders every 60 seconds in background
while true; do
    php artisan reminders:send >> /dev/null 2>&1
    sleep 60
done &

echo "Reminder loop started"

php -d max_execution_time=120 artisan serve --host=0.0.0.0 --port=$PORT
