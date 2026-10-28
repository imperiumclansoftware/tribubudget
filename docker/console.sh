#!/bin/sh
# Symfony console of TribuBudget, as the user of the application:
#   docker exec tribubudget console app:setup:code
exec su-exec www-data php /app/bin/console "$@"
