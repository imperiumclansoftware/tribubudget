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

L’image contient **tout** : l’interface Angular et l’API Symfony servies par [FrankenPHP](https://frankenphp.dev)
sur le port 80, leur base **PostgreSQL 16** et le planificateur des échéances. Aucun réglage : les secrets et la
base sont créés au premier lancement.

- Code source : https://github.com/imperiumclansoftware/tribubudget
- Licence : MIT

## Tags

| Tag | Contenu |
|---|---|
| `latest` | Dernière version publiée |
| `0.2.0` | Image tout-en-un (base intégrée, secrets créés au premier lancement), page d’installation avec code |
| `0.1.0` | Première version (base PostgreSQL séparée) |

Plateforme : `linux/amd64`.

## Installation

### En une commande

```bash
docker run -d --name tribubudget --restart unless-stopped -p 8080:80 -v tribubudget:/data iemperor/tribubudget
```

### Ou avec Docker Compose / Portainer

```yaml
services:
  tribubudget:
    image: iemperor/tribubudget:latest
    container_name: tribubudget
    restart: unless-stopped
    ports:
      - "8080:80"
    environment:
      APP_TIMEZONE: Europe/Paris
    volumes:
      - tribubudget_data:/data
    # Laisse à la base le temps de s’arrêter proprement
    stop_grace_period: 30s

volumes:
  tribubudget_data:
```

```bash
docker compose up -d
```

### Premier lancement

1. Le conteneur crée ses secrets, sa base et ses clés dans le volume `/data` (une minute au plus).
2. Récupérez le **code d’installation** dans ses journaux :
   ```bash
   docker logs tribubudget
   ```
   (ou `docker exec tribubudget console app:setup:code`)
3. Ouvrez `http://<serveur>:8080` : la page d’installation demande ce code, puis crée **votre famille et votre
   compte de parent**. Ajoutez ensuite l’autre parent et les enfants depuis la page *Famille*.

Le code empêche quelqu’un d’autre d’installer TribuBudget à votre place s’il atteint la page avant vous. Une
instance accueille une famille : il n’y a pas d’inscription libre.

## Variables (toutes facultatives)

| Variable | Rôle |
|---|---|
| `APP_TIMEZONE` | Fuseau de « aujourd’hui » : dates de l’argent de poche et des échéances (`Europe/Paris`) |
| `SCHEDULER_INTERVAL` | Secondes entre deux passages du planificateur (`3600`) |
| `APP_SECRET`, `JWT_PASSPHRASE` | Pour imposer vos secrets ; sinon créés au premier lancement dans `/data/secrets` |
| `DATABASE_URL` | Pour utiliser votre propre PostgreSQL au lieu de la base intégrée |

## Le volume `/data`

| Dossier | Contenu |
|---|---|
| `postgres/` | La base de données |
| `secrets/` | Secrets créés au premier lancement (lisibles par root seulement) |
| `jwt/` | Clés de signature des sessions |

**Sauvegardez ce volume.** Pour un export de la base :

```bash
docker exec tribubudget su-exec postgres pg_dump tribubudget > tribubudget.sql
```

## HTTPS

L’image sert en HTTP sur le port 80. Pour un accès depuis Internet, placez-la derrière un reverse proxy
qui fournit le HTTPS (Caddy, Traefik, Nginx Proxy Manager…).

## Mise à jour

```bash
docker pull iemperor/tribubudget && docker rm -f tribubudget   # puis la commande docker run ci-dessus
# ou : docker compose pull && docker compose up -d
```

Les migrations de la base sont jouées au démarrage.

### Depuis 0.1.0 (base PostgreSQL séparée)

Gardez votre compose de la 0.1.0 et ses variables (`DATABASE_URL`, `APP_SECRET`, `JWT_PASSPHRASE`) : avec
`DATABASE_URL`, l’image utilise votre base et ne démarre pas la sienne. Le service `scheduler` devient
facultatif (le planificateur tourne aussi dans le conteneur principal).
