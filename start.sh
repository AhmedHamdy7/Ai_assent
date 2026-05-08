#!/bin/bash

echo "=== Starting app ==="
echo "PORT=${PORT}"
echo "PHP=$(php -r 'echo PHP_VERSION;')"

echo "--- config:cache ---"
php artisan config:cache 2>&1 || { echo "ERROR: config:cache failed"; exit 1; }

echo "--- migrate ---"
php artisan migrate --force 2>&1 && echo "migrate OK" || echo "migrate FAILED — continuing"

echo "--- starting queue worker ---"
php artisan queue:work --queue=telegram --timeout=180 --tries=1 --sleep=3 &
echo "Queue worker PID=$!"

echo "--- starting reminder loop ---"
while true; do
    php artisan reminders:send 2>&1
    sleep 60
done &
echo "Reminder loop PID=$!"

echo "=== Starting web server on port ${PORT:-8000} ==="
exec php -d max_execution_time=120 artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
