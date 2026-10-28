#!/bin/sh
# Starts TribuBudget, all in one (run as root by tini):
#  1. the secrets, created at the first start in /data/secrets unless given as variables;
#  2. the database: the embedded PostgreSQL (/data/postgres), unless DATABASE_URL is given;
#  3. migrations, JWT keys and installation code, then the scheduler and the web server, each
#     under its own user. On stop, the services end and PostgreSQL shuts down cleanly.
# Any other command (e.g. tribubudget-scheduler next to an external database) is run as www-data.
set -e

DATA=/data
PGDATA="$DATA/postgres"
PG_MAJOR=16

mkdir -p "$DATA/secrets" "$DATA/jwt" "$DATA/setup" "$DATA/caddy" "$DATA/caddy-config"
chmod 700 "$DATA/secrets"

# A secret: the variable if given, otherwise the one generated at the first start
secret() {
	file="$DATA/secrets/$1"
	if [ ! -s "$file" ]; then
		openssl rand -hex 32 > "$file"
		chmod 600 "$file"
		echo "TribuBudget : secret « $1 » créé." >&2
	fi
	cat "$file"
}

APP_SECRET="${APP_SECRET:-$(secret app_secret)}"
JWT_PASSPHRASE="${JWT_PASSPHRASE:-$(secret jwt_passphrase)}"
export APP_SECRET JWT_PASSPHRASE

embedded_database=false
if [ -z "${DATABASE_URL:-}" ]; then
	embedded_database=true
	DATABASE_URL="postgresql://tribubudget:$(secret database_password)@127.0.0.1:5432/tribubudget?serverVersion=$PG_MAJOR&charset=utf8"
	export DATABASE_URL
fi

# The web and scheduler processes share the locks that prevent paying an allowance twice:
# PostgreSQL advisory locks, on the application's database
case "$DATABASE_URL" in
	postgresql://* | postgres://* | pgsql://*)
		export LOCK_DSN="${LOCK_DSN:-$(printf '%s' "$DATABASE_URL" | sed -E 's#^(postgresql|postgres|pgsql)://#\1+advisory://#')}"
		;;
esac

# Also for "docker exec tribubudget console …", which does not go through this script
umask 027
{
	echo "APP_SECRET=$APP_SECRET"
	echo "JWT_PASSPHRASE=$JWT_PASSPHRASE"
	echo "DATABASE_URL=\"$DATABASE_URL\""
	echo "LOCK_DSN=\"$LOCK_DSN\""
} > /app/.env.local
umask 022
chown root:www-data /app/.env.local
chown -R www-data:www-data "$DATA/jwt" "$DATA/setup" "$DATA/caddy" "$DATA/caddy-config"

as_app() {
	su-exec www-data "$@"
}

start_database() {
	if [ -s "$PGDATA/PG_VERSION" ] && [ "$(cat "$PGDATA/PG_VERSION")" != "$PG_MAJOR" ]; then
		echo "TribuBudget : la base de /data/postgres est en PostgreSQL $(cat "$PGDATA/PG_VERSION"), cette image embarque PostgreSQL $PG_MAJOR." >&2
		exit 1
	fi
	if [ ! -s "$PGDATA/PG_VERSION" ]; then
		echo 'TribuBudget : création de la base de données…'
		mkdir -p "$PGDATA"
		chown postgres:postgres "$PGDATA"
		chmod 700 "$PGDATA"
		su-exec postgres initdb -D "$PGDATA" -U postgres --encoding=UTF8 --locale=C \
			--auth-local=peer --auth-host=scram-sha-256 > /dev/null
	fi
	chown -R postgres:postgres "$PGDATA"
	# Reachable from inside the container only
	su-exec postgres pg_ctl -D "$PGDATA" -w -t 120 -s \
		-o "-c listen_addresses=127.0.0.1 -c unix_socket_directories=/run/postgresql" start
	if [ -z "$(su-exec postgres psql -h /run/postgresql -tAc "SELECT 1 FROM pg_roles WHERE rolname = 'tribubudget'")" ]; then
		su-exec postgres psql -h /run/postgresql -q -v ON_ERROR_STOP=1 \
			-c "CREATE ROLE tribubudget LOGIN PASSWORD '$(cat "$DATA/secrets/database_password")'" \
			-c 'CREATE DATABASE tribubudget OWNER tribubudget'
		echo 'TribuBudget : base de données créée.'
	fi
}

stop_database() {
	su-exec postgres pg_ctl -D "$PGDATA" -w -t 60 -s -m fast stop || true
}

wait_for_database() {
	echo 'TribuBudget : attente de la base de données…'
	tries=0
	until error=$(as_app php bin/console dbal:run-sql -q 'SELECT 1' 2>&1); do
		tries=$((tries + 1))
		# The reason (password refused, host unknown…), without waiting for the end
		reason=$(printf '%s\n' "$error" | grep -Eo 'FATAL: .*|could not translate host name .*|Connection refused|timeout expired' | head -n 1 | cut -c1-200)
		if [ "$tries" -ge 60 ]; then
			echo "TribuBudget : base de données injoignable après 2 minutes : ${reason:-$error}" >&2
			exit 1
		fi
		if [ $((tries % 5)) -eq 1 ]; then
			echo "TribuBudget : base de données pas encore prête (${reason:-réponse inattendue}), nouvel essai…"
		fi
		sleep 2
	done
	echo 'TribuBudget : base de données prête.'
}

# Another command: run it as the application, against the given database
if [ "$1" != 'frankenphp' ]; then
	if $embedded_database; then
		echo 'TribuBudget : pour une commande, utilisez « docker exec tribubudget console … » dans le conteneur en marche.' >&2
		exit 1
	fi
	wait_for_database
	exec su-exec www-data "$@"
fi

if $embedded_database; then
	start_database
fi
wait_for_database
as_app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
as_app php bin/console lexik:jwt:generate-keypair --skip-if-exists
# Until the family is created: the code asked by the installation page
as_app php bin/console app:setup:code

as_app tribubudget-scheduler &
scheduler=$!
su-exec www-data "$@" &
web=$!

shutdown() {
	echo 'TribuBudget : arrêt…'
	kill -TERM "$web" "$scheduler" 2>/dev/null || true
	wait "$web" 2>/dev/null || true
	if $embedded_database; then
		stop_database
	fi
	exit 0
}
trap shutdown TERM INT

# The web server ending on its own stops the container (and the database with it)
wait "$web" || true
kill -TERM "$scheduler" 2>/dev/null || true
if $embedded_database; then
	stop_database
fi
exit 1
