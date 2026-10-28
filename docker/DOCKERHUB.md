# TribuBudget

**Le budget de toute la famille.**

TribuBudget réunit parents et enfants autour du budget du foyer, dans un espace privé, auto-hébergé, en
français :

- **Deux profils** : les parents voient et gèrent tout ; les enfants voient leur tirelire, notent leurs
  dépenses et demandent une avance ou un achat
- **Comptes et opérations** par catégorie, **virements** entre comptes
- **Opérations récurrentes** (loyer, abonnements, salaire) enregistrées seules à leur date
- **Budgets mensuels** par catégorie, dépassements signalés
- **Projets d’épargne** financés par ordre de priorité, au-dessus d’un plancher
- **Argent de poche automatique**, avances remboursées sur les versements suivants
- **Tableau de bord** : solde, mois en cours, six derniers mois, projets réalisables, prochaines échéances
- Interface pensée pour le téléphone, jusqu’à l’écran large ; thème clair ou sombre selon le système

L’image contient toute l’application : l’interface Angular et l’API Symfony, servies par
[FrankenPHP](https://frankenphp.dev) sur le port 80. Il lui faut seulement une base PostgreSQL.

- Code source : https://github.com/imperiumclansoftware/tribubudget
- Licence : MIT

## Tags

| Tag | Contenu |
|---|---|
| `latest` | Dernière version publiée |
| `0.1.0` | Première version |

Plateforme : `linux/amd64`.

## Installation

### 1. Le fichier `compose.yaml`

Dans un dossier vide (par exemple `tribubudget/`), créez ce fichier :

```yaml
x-tribubudget-environment: &tribubudget-environment
  APP_SECRET: ${APP_SECRET:?APP_SECRET à renseigner (openssl rand -hex 32)}
  JWT_PASSPHRASE: ${JWT_PASSPHRASE:?JWT_PASSPHRASE à renseigner (openssl rand -hex 32)}
  DATABASE_URL: postgresql://tribubudget:${POSTGRES_PASSWORD}@database:5432/tribubudget?serverVersion=16&charset=utf8
  APP_TIMEZONE: ${APP_TIMEZONE:-Europe/Paris}

services:
  tribubudget:
    image: iemperor/tribubudget:${TRIBUBUDGET_VERSION:-latest}
    restart: unless-stopped
    ports:
      - "${TRIBUBUDGET_PORT:-8080}:80"
    environment: *tribubudget-environment
    volumes:
      - tribubudget_data:/data
    depends_on:
      database:
        condition: service_healthy

  # Argent de poche et opérations récurrentes enregistrés à leur date, même si personne n’ouvre TribuBudget
  scheduler:
    image: iemperor/tribubudget:${TRIBUBUDGET_VERSION:-latest}
    restart: unless-stopped
    command: ["tribubudget-scheduler"]
    environment: *tribubudget-environment
    healthcheck:
      disable: true
    depends_on:
      tribubudget:
        condition: service_healthy

  database:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: tribubudget
      POSTGRES_USER: tribubudget
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:?POSTGRES_PASSWORD à renseigner}
    healthcheck:
      test: ["CMD", "pg_isready", "-d", "tribubudget", "-U", "tribubudget"]
      interval: 5s
      timeout: 5s
      retries: 10
    volumes:
      - database_data:/var/lib/postgresql/data

volumes:
  tribubudget_data:
  database_data:
```

### 2. Le fichier `.env`

À côté, un fichier `.env` (générez chaque secret avec `openssl rand -hex 32`) :

```dotenv
TRIBUBUDGET_PORT=8080
TRIBUBUDGET_VERSION=latest
APP_SECRET=
JWT_PASSPHRASE=
# Ne le changez plus une fois la base créée
POSTGRES_PASSWORD=
APP_TIMEZONE=Europe/Paris
```

Avec Portainer : collez le `compose.yaml` comme stack et saisissez ces variables dans *Environment variables*.

### 3. Démarrage

```bash
docker compose up -d
```

Au premier démarrage, la base est créée et les clés de connexion générées (volume `tribubudget_data`).
Ouvrez ensuite `http://<serveur>:8080`, puis **Créer l’espace de ma famille** : vous en devenez le premier
parent, et vous ajoutez l’autre parent et les enfants depuis la page *Famille*.

## Variables

| Variable | Rôle |
|---|---|
| `APP_SECRET` | Secret de Symfony (obligatoire) |
| `JWT_PASSPHRASE` | Protège la clé de signature des sessions (obligatoire) |
| `DATABASE_URL` | Base PostgreSQL (obligatoire ; composée par le `compose.yaml`) |
| `APP_TIMEZONE` | Fuseau de « aujourd’hui » : dates de l’argent de poche et des échéances (`Europe/Paris`) |
| `SCHEDULER_INTERVAL` | Secondes entre deux passages du planificateur (`3600`) |

## HTTPS

L’image sert en HTTP sur le port 80. Pour un accès depuis Internet, placez-la derrière un reverse proxy
qui fournit le HTTPS (Caddy, Traefik, Nginx Proxy Manager…).

## Mise à jour

```bash
docker compose pull && docker compose up -d
```

Les migrations de la base sont jouées au démarrage.

## Sauvegarde

Sauvegardez la base (`docker compose exec database pg_dump -U tribubudget tribubudget > tribubudget.sql`) et le
volume `tribubudget_data` (clés de connexion).
