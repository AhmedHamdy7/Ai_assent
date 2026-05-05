#!/bin/bash
set -e

php artisan config:cache
php artisan migrate --force

php artisan schedule:work &
echo "Scheduler started"

php -d max_execution_time=120 artisan serve --host=0.0.0.0 --port=$PORT
