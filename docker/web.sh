#!/bin/sh
# The web server of TribuBudget: PHP-FPM for the API, nginx in front of it. Their master processes
# start as root (port 80, the container's logs), their workers run as www-data. Either ending
# stops the other, then the container (see tribubudget-entrypoint).
set -e

# Temporary files of the requests, written by the workers
mkdir -p /tmp/nginx
chown www-data:www-data /tmp/nginx
php-fpm --nodaemonize --force-stderr &
fpm=$!
nginx -e stderr -g 'daemon off;' &
nginx=$!

stop() {
	kill -TERM "$nginx" "$fpm" 2>/dev/null || true
	wait "$nginx" "$fpm" 2>/dev/null || true
}
trap 'stop; exit 0' TERM INT

while kill -0 "$fpm" 2>/dev/null && kill -0 "$nginx" 2>/dev/null; do
	sleep 2
done
echo 'TribuBudget : le serveur web s’est arrêté.' >&2
stop
exit 1
