# TribuBudget

**Le budget de toute la famille.**

TribuBudget réunit parents, ados et enfants autour du budget du foyer, dans un espace privé, auto-hébergé, en
français :

- **Trois profils** : les parents voient et gèrent tout ; les ados tiennent leurs propres comptes, sous
  l’œil des parents, et demandent le remboursement de leurs dépenses ; les enfants voient leur tirelire,
  notent leurs dépenses et demandent une avance ou un achat
- **Comptes et opérations** par catégorie, **virements** entre comptes, **pointage** sur le relevé
- **Opérations récurrentes** (loyer, abonnements, salaire) enregistrées seules à leur date
- **Budgets mensuels** par catégorie, dépassements signalés
- **Projets d’épargne** financés par ordre de priorité, au-dessus d’un plancher
- **Argent de poche automatique**, avances remboursées sur les versements suivants
- **Véhicules** : pleins avec un budget carburant mensuel par véhicule et la consommation réelle,
  entretiens prévus chacun avec son budget, kilométrage ; marques et modèles du catalogue ouvert de l’ADEME
- **Tableau de bord** : solde, mois en cours, reste prévu fin du mois, six derniers mois, projets réalisables, prochaines échéances
- Interface pensée pour le téléphone, jusqu’à l’écran large ; thème clair ou sombre selon le système

L’image contient **tout** : l’interface Angular et l’API Symfony servies par nginx et PHP-FPM sur le port 80, leur base **PostgreSQL 16** et le planificateur des échéances. Aucun réglage : les secrets et la
base sont créés au premier lancement.

- Code source : https://github.com/imperiumclansoftware/tribubudget
- Licence : MIT

## Tags

| Tag | Contenu |
|---|---|
| `latest` | Dernière version publiée |
| `0.6.0` | Connexion par cookie de session (plus de jeton dans le navigateur), mots de passe de 12 caractères vérifiés contre les fuites connues, journal de sécurité, en-têtes de sécurité ; chaque membre se reconnecte une fois |
| `0.5.1` | Refus d’accès affichés en clair (« Seuls les parents ont accès aux comptes. ») au lieu de « Forbidden » ; messages techniques du serveur masqués |
| `0.5.0` | Véhicules : pleins avec budget carburant mensuel, entretiens avec leur budget, kilométrage, catalogue des modèles (ADEME) ; icône de l’application |
| `0.4.0` | Serveur web nginx + PHP-FPM au lieu de FrankenPHP : aucune CVE connue dans l’image |
| `0.3.1` | Correctifs de sécurité d’Alpine (`curl` 8.23.0) |
| `0.3.0` | Profil ado avec ses propres comptes et demandes de remboursement, pointage des opérations, reste prévu fin du mois |
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
   compte de parent**. Ajoutez ensuite l’autre parent, les ados et les enfants depuis la page *Famille*.

Le code empêche quelqu’un d’autre d’installer TribuBudget à votre place s’il atteint la page avant vous. Une
instance accueille une famille : il n’y a pas d’inscription libre.

## Variables (toutes facultatives)

| Variable | Rôle |
|---|---|
| `APP_TIMEZONE` | Fuseau de « aujourd’hui » : dates de l’argent de poche et des échéances (`Europe/Paris`) |
| `SCHEDULER_INTERVAL` | Secondes entre deux passages du planificateur (`3600`) |
| `APP_SECRET` | Pour imposer votre secret ; sinon créé au premier lancement dans `/data/secrets` |
| `TRUSTED_PROXIES` | Adresses du reverse proxy HTTPS à croire (IP des visiteurs, HTTPS) : `private_ranges` par défaut |
| `DATABASE_URL` | Pour utiliser votre propre PostgreSQL au lieu de la base intégrée |

## Le volume `/data`

| Dossier | Contenu |
|---|---|
| `postgres/` | La base de données |
| `secrets/` | Secrets créés au premier lancement (lisibles par root seulement) |

**Sauvegardez ce volume.** Pour un export de la base :

```bash
docker exec tribubudget su-exec postgres pg_dump tribubudget > tribubudget.sql
```

## HTTPS

L’image sert en HTTP sur le port 80. Pour un accès depuis Internet, placez-la derrière un reverse proxy
qui fournit le HTTPS (Caddy, Traefik, Nginx Proxy Manager…). Le proxy doit transmettre `X-Forwarded-For` et
`X-Forwarded-Proto` (ce que font ceux-là par défaut) : l’application voit alors l’adresse des visiteurs (limite
des essais de connexion, journal de sécurité), marque le cookie de session `Secure` et envoie HSTS. Par défaut,
un proxy sur une adresse privée (même machine, réseau Docker) est cru ; sinon, donnez son adresse dans
`TRUSTED_PROXIES`.

## Mot de passe oublié

Un parent change le mot de passe ou le code de n’importe quel autre membre depuis *Famille*. Si le seul parent
a oublié le sien :

```bash
docker exec -it tribubudget console app:user:reset-password <identifiant>
```

## Mise à jour

```bash
docker pull iemperor/tribubudget && docker rm -f tribubudget   # puis la commande docker run ci-dessus
# ou : docker compose pull && docker compose up -d
```

Les migrations de la base sont jouées au démarrage.

### Depuis 0.1.0 (base PostgreSQL séparée)

Gardez votre compose de la 0.1.0 et ses variables (`DATABASE_URL`, `APP_SECRET` ; `JWT_PASSPHRASE` ne sert plus depuis 0.6.0) : avec
`DATABASE_URL`, l’image utilise votre base et ne démarre pas la sienne. Le service `scheduler` devient
facultatif (le planificateur tourne aussi dans le conteneur principal).
