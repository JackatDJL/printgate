#!/bin/sh
set -eu

mkdir -p /var/lib/printgate /var/www/html/storage/app/private
chown www-data:www-data /var/lib/printgate /var/www/html/storage/app/private
chmod 0770 /var/lib/printgate /var/www/html/storage/app/private

if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
fi

su -s /bin/sh www-data -c 'php artisan migrate --force'

exec "$@"
