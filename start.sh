#!/bin/bash

PORT="${PORT:-8000}"

echo "===================="
echo "Starting on port $PORT"
echo "PHP $(php -r 'echo PHP_VERSION;')"
echo "===================="

# Try config:cache, but don't fail if it errors
php artisan config:clear 2>&1 || true
php artisan migrate --force 2>&1 || echo "[WARN] migrate skipped/failed"

# Start queue worker in background
php artisan queue:work --queue=telegram --timeout=180 --tries=1 --sleep=3 >/tmp/queue.log 2>&1 &
echo "[OK] queue worker pid=$!"

# Start reminder loop in background
(while true; do php artisan reminders:send 2>&1; sleep 60; done) >/tmp/reminders.log 2>&1 &
echo "[OK] reminder loop pid=$!"

echo "[INFO] starting PHP built-in server on 0.0.0.0:$PORT"
exec php -S 0.0.0.0:$PORT -t public public/index.php
