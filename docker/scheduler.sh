#!/bin/sh
# Records what falls due (allowances, recurring operations) every hour, even when nobody opens
# TribuBudget. Run as: tribubudget-entrypoint tribubudget-scheduler (the default entrypoint does it).
set -e

interval="${SCHEDULER_INTERVAL:-3600}"
echo "TribuBudget : échéances enregistrées toutes les ${interval} secondes."
while true; do
	php bin/console app:schedules:run --no-interaction || echo 'TribuBudget : échec de l’enregistrement des échéances, nouvel essai au prochain passage.' >&2
	sleep "$interval"
done
