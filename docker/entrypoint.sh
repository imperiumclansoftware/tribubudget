#!/bin/sh
# Starts TribuBudget: checks the configuration, prepares the database and the keys, then runs the
# web server (default) or the given command (e.g. the scheduler).
set -e

missing=''
for name in APP_SECRET DATABASE_URL JWT_PASSPHRASE; do
	eval "value=\${$name:-}"
	[ -n "$value" ] || missing="$missing $name"
done
if [ -n "$missing" ]; then
	echo "TribuBudget : variables d'environnement manquantes :$missing (voir docker/.env.example)." >&2
	exit 1
fi

# The web and scheduler containers must share the locks that prevent paying an allowance twice:
# PostgreSQL advisory locks, on the application's database
case "$DATABASE_URL" in
	postgresql://* | postgres://* | pgsql://*)
		export LOCK_DSN="${LOCK_DSN:-$(printf '%s' "$DATABASE_URL" | sed -E 's#^(postgresql|postgres|pgsql)://#\1+advisory://#')}"
		;;
esac

mkdir -p /data/jwt /data/caddy /data/caddy-config

echo 'TribuBudget : attente de la base de données…'
tries=0
until error=$(php bin/console dbal:run-sql -q 'SELECT 1' 2>&1); do
	tries=$((tries + 1))
	# The reason (password refused, host unknown…), without waiting for the end
	reason=$(printf '%s\n' "$error" | grep -Eo 'FATAL: .*|could not translate host name .*|Connection refused|timeout expired' | head -n 1 | cut -c1-200)
	if [ "$tries" -ge 60 ]; then
		echo "TribuBudget : base de données injoignable après 2 minutes : ${reason:-$error}" >&2
		echo 'Vérifiez POSTGRES_PASSWORD : il ne doit pas avoir changé depuis la création du volume de la base.' >&2
		exit 1
	fi
	if [ $((tries % 5)) -eq 1 ]; then
		echo "TribuBudget : base de données pas encore prête (${reason:-réponse inattendue}), nouvel essai…"
	fi
	sleep 2
done
echo 'TribuBudget : base de données prête.'

# The web container prepares the database and the keys; the scheduler only uses them
if [ "$1" = 'frankenphp' ]; then
	php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

exec "$@"
