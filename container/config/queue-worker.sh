#!/bin/sh
set -eu

cd /var/www/html

# Ajustar --queue a las colas reales del proyecto (ver config/queue.php /
# jobs despachados con ->onQueue()). Varios consumidores son seguros.
exec php artisan queue:work \
    --queue=default \
    --sleep=3 \
    --tries=3 \
    --timeout=90 \
    --max-time=3600
