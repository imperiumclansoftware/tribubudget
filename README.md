# TribuBudget

**Le budget de toute la famille.** Les parents suivent les comptes, les dépenses et les budgets du foyer ;
les enfants gèrent leur argent de poche et font leurs demandes aux parents.

| Partie     | Stack                                                  | URL de dev            |
|------------|--------------------------------------------------------|-----------------------|
| `backend`  | Symfony 8.1, Doctrine ORM, PostgreSQL 16, LexikJWT     | http://127.0.0.1:8002 |
| `frontend` | Angular 22 (standalone, signals, SCSS)                 | http://localhost:4400 |

## Fonctionnement

- **Création** (`/inscription`) : un parent crée l’espace de sa famille et en devient le premier membre. La
  famille reçoit un *Compte courant* et des catégories prêtes à l’emploi (`App\Money\FamilySetup`). Le
  parent ajoute ensuite l’autre parent et les enfants (*Famille*).
- **Deux profils** (`App\Enum\FamilyRole`) :
  - **Parent** : accès à tout. Mot de passe de 8 caractères au moins.
  - **Enfant** : seulement *sa* tirelire et *ses* demandes. Il se connecte avec un code de 4 à 12 chiffres ;
    un parent le change s’il l’oublie. La connexion est freinée après 5 essais ratés en 15 minutes
    (`login_throttling`), un code court se devinant vite.
- **Droits** : les routes du budget du foyer exigent `ROLE_PARENT` (`#[IsGranted]` sur les contrôleurs) ; les
  règles fines sont dans les voters (`src/Security`) :
  - `ChildVoter` : un enfant voit et dépense son propre argent de poche, jamais celui d’un frère ou d’une sœur ;
    seuls les parents donnent de l’argent ou règlent l’argent de poche ;
  - `PocketMoneyEntryVoter` : un enfant n’efface que les dépenses qu’il a notées lui-même ;
  - `MoneyRequestVoter` : les enfants demandent, les parents répondent, une seule fois.

  Les données d’une autre famille n’existent pas pour l’API (404, trait `FamilyScope`).
- **Parents**
  - *Tableau de bord* : solde de tous les comptes, revenus et dépenses du mois, dépenses par catégorie face
    aux budgets (dépassements signalés), revenus et dépenses des 6 derniers mois, tirelires des enfants.
  - *Opérations* : revenus et dépenses par mois, filtrables par compte et par catégorie.
  - *Virements* (bouton *Virement* des opérations, ou depuis *Comptes*) : de l’argent déplacé d’un compte de la
    famille à un autre, enregistré comme deux opérations liées (sortie et entrée, le même jour). Ce n’est ni
    un revenu ni une dépense : les virements n’entrent ni dans les totaux du mois, ni dans les budgets, ni
    dans l’historique. Ils se modifient et se suppriment en entier, jamais une seule de leurs opérations.
    Un virement vers un compte d’épargne alimente ses projets.
  - *Opérations récurrentes* (onglet *Récurrentes*) : loyer, abonnements, salaire… chaque semaine, mois,
    trimestre ou année, avec une date de fin facultative (fin d’un crédit). À chaque échéance, l’opération
    est enregistrée toute seule sur le compte (marquée d’une icône), y compris les échéances manquées
    depuis la dernière visite ou passées à la création. Une mise en pause arrête les enregistrements ;
    supprimer une récurrente garde les opérations déjà enregistrées. Le tableau de bord liste les
    échéances des 30 prochains jours.
  - *Budgets* : un plafond mensuel par catégorie de dépenses, qui vaut pour chaque mois.
  - *Projets* : ce que la famille finance avec son épargne (vacances, vélo…), rattaché à un compte d’épargne
    avec un montant visé et une priorité (`App\Money\SavingsAllocator`). L’argent sous le **plancher** du
    compte reste de côté ; le reste va aux projets dans l’ordre de priorité, chacun rempli avant que le
    suivant ne reçoive quoi que ce soit. Un projet couvert est *réalisable maintenant* (le tableau de bord
    les liste). *C’est fait* l’archive et enregistre le retrait sur le compte (par défaut) : le reste de
    l’épargne passe aux projets suivants.
  - *Comptes* : comptes du foyer (courant, épargne, espèces) avec solde de départ, et catégories. Un compte
    d’épargne peut avoir un plancher. Un compte qui a des opérations ne peut pas être supprimé, un compte
    d’épargne qui porte des projets ne peut pas changer de type ; une catégorie supprimée laisse ses opérations sans
    catégorie.
  - *Famille* : ajout, renommage, nouveau code ou mot de passe, suppression d’un membre (pas soi-même).
- **Argent de poche** (`App\Money\AllowancePayer`) : montant chaque semaine ou chaque mois, à partir d’une date.
  Un versement mensuel garde son jour (le 31 tombe le 30 avril, puis le 31 mai). Chaque versement peut être
  pris sur un compte du foyer : il y est noté comme dépense *Argent de poche*.
- **Échéances** (`App\Money\Schedules`) : l’argent de poche et les opérations récurrentes dus sont enregistrés
  dès qu’un membre de la famille ouvre l’application, y compris ceux manqués depuis la dernière visite
  (verrou par famille contre les doublons), et chaque jour par la commande `app:schedules:run`. Les dates
  mensuelles, trimestrielles et annuelles gardent leur jour (`App\Enum\Frequency`).
- **Tirelire d’un enfant** : solde et historique. L’enfant note ses dépenses (jamais plus que ce qu’il a) ; un
  parent donne ou retire de l’argent (cadeau, correction).
- **Demandes** : un enfant demande une **avance** (créditée tout de suite si un parent accepte, puis reprise sur
  les prochains versements d’argent de poche) ou un **achat** (les parents l’achètent s’ils sont d’accord). Le
  parent répond avec un mot facultatif ; l’enfant peut retirer une demande en attente.
- Les montants circulent en **centimes** (entiers) entre l’API et l’application.

## Thème

Mobile first, clair ou sombre selon le système. Les tokens sémantiques et la mise en page viennent de
`themecss/` (à la racine des projets) :

- `frontend/src/styles/_tokens.scss` : variables (`--bg-main`, `--text-primary`, `--primary`, `--success`…,
  `--touch-target: 48px`) et mode sombre. Ajouts propres à TribuBudget : `--success-text`, `--danger-text` et
  `--warning-text` pour le texte (les couleurs du thème sont trop claires pour du texte sur fond clair),
  `--primary-bg`, et les couleurs des graphiques ;
- `_base.scss` : reset ; `_layout.scss` : `.app-container` (480 px, 768 px sur tablette), `.card-balance`,
  `.transaction-list`, `.mobile-nav` (navigation basse fixe) ;
- `_components.scss` : boutons, champs, cartes, listes… ; aucune zone cliquable sous 48 px.

Sur téléphone, les fenêtres s’ouvrent en panneau depuis le bas ; dès 600 px, au centre.

Paliers : téléphone (base), tablette (600 et 768 px : formulaires et cartes sur deux colonnes), **ordinateur**
(1024 px : la barre basse devient une navigation latérale avec *Comptes*, pages centrées à 1200 px, tableau de
bord avec le solde et les chiffres du mois sur une ligne, tirelire en deux colonnes, filtres des opérations sur
une ligne dès 1280 px) et **écran large** (1440 px : pages à 1360 px, cartes du tableau de bord sur trois
colonnes). Tokens : `--sidebar-width`, `--content-max`.

## Développement

```bash
# Backend
cd backend
docker compose up -d                      # PostgreSQL sur le port 5434
composer install
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate -n
php -S 127.0.0.1:8002 -t public           # ou : symfony serve --port=8002

# Frontend
cd frontend
npm install
npm start                                 # http://localhost:4400 (proxy /api → :8002)
npm run start:code-server                 # derrière code-server : /absproxy/4400/
```

Ici, Docker tourne sur l’hôte `docker` : `backend/.env.local` et `backend/.env.test.local` pointent
`DATABASE_URL` sur `docker:5434`.

Hors Docker, lancer au moins chaque jour : `php bin/console app:schedules:run` (cron). L’image Docker a son
service `scheduler`, qui le fait toutes les heures.

## Installation (Docker)

Image `iemperor/tribubudget` : l’application et l’API servies par FrankenPHP, plus un service `scheduler` pour
les échéances. Fichiers prêts à l’emploi : [`docker/compose.yaml`](docker/compose.yaml) et
[`docker/.env.example`](docker/.env.example) ; guide complet : [`docker/DOCKERHUB.md`](docker/DOCKERHUB.md)
(page Docker Hub).

```bash
docker build --pull -t iemperor/tribubudget:0.1.0 --build-arg APP_VERSION=0.1.0 .
```

## Publier une version

1. Ajouter la section de la version dans [`CHANGELOG.md`](CHANGELOG.md) (et la ligne du tag dans
   `docker/DOCKERHUB.md`), commiter.
2. Construire et pousser l’image : `docker build --pull -t iemperor/tribubudget:X.Y.Z --build-arg APP_VERSION=X.Y.Z .`,
   `docker tag iemperor/tribubudget:X.Y.Z iemperor/tribubudget:latest`, puis `docker push` des deux tags.
3. `git tag vX.Y.Z && git push origin main vX.Y.Z` : le workflow *Release* vérifie le code (CI) puis publie la
   release GitHub avec les notes de `CHANGELOG.md`.

## Tests et qualité

```bash
cd backend
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/phpunit                  # tests fonctionnels de l’API (droits, argent de poche, demandes…)
vendor/bin/phpstan analyse       # niveau 6
vendor/bin/php-cs-fixer fix

cd frontend
npm test
npm run format:check
```
