#!/bin/bash
set -e

php artisan config:cache
php artisan migrate --force

# Queue worker for Telegram updates
php artisan queue:work --queue=telegram --timeout=180 --tries=1 --sleep=3 &
echo "Queue worker started"

# Reminder loop every 60 seconds
while true; do
    php artisan reminders:send >> /dev/null 2>&1
    sleep 60
done &
echo "Reminder loop started"

# Web server (foreground)
php -d max_execution_time=120 artisan serve --host=0.0.0.0 --port=$PORT
