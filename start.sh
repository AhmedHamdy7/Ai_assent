#!/bin/bash

echo "=== Starting app ==="

php artisan config:cache
echo "✓ config:cache done"

php artisan migrate --force && echo "✓ migrate done" || echo "✗ migrate FAILED — continuing anyway"

# Queue worker for Telegram updates
php artisan queue:work --queue=telegram --timeout=180 --tries=1 --sleep=3 &
echo "✓ Queue worker started (PID $!)"

# Reminder loop every 60 seconds
while true; do
    php artisan reminders:send >> /dev/null 2>&1
    sleep 60
done &
echo "✓ Reminder loop started (PID $!)"

# Web server (foreground)
echo "=== Starting web server on port ${PORT:-8000} ==="
php -d max_execution_time=120 artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
