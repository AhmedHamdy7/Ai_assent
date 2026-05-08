#!/usr/bin/env bash

PORT="${PORT:-8080}"

echo "[BOOT] starting at $(date)"
echo "[BOOT] PORT=$PORT"

echo "[BOOT] running migrations"
php artisan migrate --force 2>&1 || echo "[BOOT] migrate failed - continuing"

echo "[BOOT] starting queue worker"
php artisan queue:work --queue=telegram --timeout=180 --tries=1 --sleep=3 -v &
QUEUE_PID=$!
echo "[BOOT] queue worker pid=$QUEUE_PID"

echo "[BOOT] starting reminder loop"
( while true; do
    php artisan reminders:send 2>&1 || echo "[REMIND] error"
    sleep 60
done ) &
REMINDER_PID=$!
echo "[BOOT] reminder pid=$REMINDER_PID"

cleanup() {
    echo "[BOOT] shutting down"
    kill "$QUEUE_PID" "$REMINDER_PID" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo "[BOOT] starting web server on 0.0.0.0:$PORT"
php -S "0.0.0.0:$PORT" -t public
