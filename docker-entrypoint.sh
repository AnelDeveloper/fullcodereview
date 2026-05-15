#!/bin/sh
set -e

php artisan migrate --force
php artisan storage:link --force

# Queue worker runs in the background. The inner loop restarts it if it
# crashes; --max-time=3600 recycles the process every hour to avoid the
# long-lived-PHP memory bloat that's common in production queue workers.
( while true; do
    php artisan queue:work database \
        --queue=default \
        --tries=1 \
        --timeout=900 \
        --max-time=3600 \
        --sleep=3 \
        || true
    sleep 2
done ) &

exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
