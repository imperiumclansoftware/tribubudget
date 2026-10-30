# TribuBudget

**Le budget de toute la famille.** Les parents suivent les comptes, les dépenses et les budgets du foyer ;
les ados tiennent leurs propres comptes, sous l’œil des parents ; les enfants gèrent leur argent de poche et
font leurs demandes aux parents.

| Partie     | Stack                                                  | URL de dev            |
|------------|--------------------------------------------------------|-----------------------|
| `backend`  | Symfony 8.1, Doctrine ORM, PostgreSQL 16, LexikJWT     | http://127.0.0.1:8002 |
| `frontend` | Angular 22 (standalone, signals, SCSS)                 | http://localhost:4400 |

## Fonctionnement

- **Installation** (`/installation`, à la première visite) : une instance = une famille. La page demande d’abord
  le **code d’installation** affiché sur le serveur (journaux du conteneur, ou `bin/console app:setup:code`),
  pour que seul l’administrateur du serveur installe, pas le premier venu ; puis elle crée la famille et son
  premier parent (`App\Controller\SetupController`, une seule fois, essais du code limités). La famille reçoit
  un *Compte courant* et des catégories prêtes à l’emploi (`App\Money\FamilySetup`). Il n’y a pas
  d’inscription libre : un parent ajoute l’autre parent, les ados et les enfants (*Famille*).
- **Trois profils** (`App\Enum\FamilyRole`) :
  - **Parent** : accès à tout. Mot de passe de 8 caractères au moins.
  - **Ado** : seulement *ses* comptes et *ses* demandes. Mot de passe de 8 caractères au moins.
  - **Enfant** : seulement *sa* tirelire et *ses* demandes. Il se connecte avec un code de 4 à 12 chiffres ;
    un parent le change s’il l’oublie. La connexion est freinée après 5 essais ratés en 15 minutes
    (`login_throttling`), un code court se devinant vite.
- **Droits** : les routes du budget du foyer exigent `ROLE_PARENT` (`#[IsGranted]` sur les contrôleurs) ;
  les opérations et les virements, `ROLE_ACCOUNT_HOLDER` (parents et ados), un ado n’atteignant que ses
  comptes (`App\Api\FamilyLookup::account()`). Les règles fines sont dans les voters (`src/Security`) :
  - `ChildVoter` : un enfant voit et dépense son propre argent de poche, jamais celui d’un frère ou d’une sœur ;
    seuls les parents donnent de l’argent ou règlent l’argent de poche ;
  - `PocketMoneyEntryVoter` : un enfant n’efface que les dépenses qu’il a notées lui-même ;
  - `TeenVoter` : un ado voit ses comptes, jamais ceux d’un frère ou d’une sœur ; seuls les parents règlent
    son argent de poche ;
  - `OperationVoter` : un ado ne modifie que les opérations et virements qu’il a notés lui-même sur ses
    comptes, jamais ce que les parents y ont mis ;
  - `MoneyRequestVoter` : les enfants et les ados demandent, les parents répondent, une seule fois.

  Les données d’une autre famille n’existent pas pour l’API (404, trait `FamilyScope`).
- **Parents**
  - *Tableau de bord* : solde de tous les comptes, revenus et dépenses du mois, dépenses par catégorie face
    aux budgets (dépassements signalés), revenus et dépenses des 6 derniers mois, tirelires des enfants.
    **Reste prévu fin du mois** (mois en cours, `App\Money\MonthForecast`) : revenus déjà reçus et
    revenus récurrents à venir, moins, pour chaque catégorie budgétée, son budget ou ce qui y est dépensé
    s’il est dépassé (ses récurrentes comprises, jamais comptées deux fois), les récurrentes hors budgets,
    l’argent de poche restant à verser (enfants et ados) et les autres dépenses déjà faites.
  - *Opérations* : revenus et dépenses par mois, filtrables par compte et par catégorie. **Pointage** : la case
    devant chaque opération la coche une fois retrouvée sur le relevé de la banque (`Operation::reconciled`,
    aussi chaque opération d’un virement). Le *solde pointé* d’un compte (solde de départ + opérations cochées)
    s’affiche quand on filtre sur ce compte, dans *Comptes* et sur les comptes d’un ado, qui pointe les siens.
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
- **Comptes d’un ado** (*Mes comptes* pour l’ado, *Famille* puis *Voir les comptes* pour les parents) : à sa
  création, un ado reçoit un *Compte courant* et une *Épargne* à lui (`Account::owner`) ; les parents en
  ajoutent, renomment ou suppriment dans *Comptes*. Ces comptes restent **en dehors du budget du foyer** :
  ni dans le solde, ni dans les revenus et dépenses du mois, ni dans les budgets ou les projets ; le tableau
  de bord les montre à part. L’ado y note ses revenus et dépenses et vire de l’argent entre ses comptes ; les
  parents voient tout, notent aussi des opérations et font des virements depuis ou vers le foyer (neutres
  pour le budget, comme tout virement, aussi depuis *Opérations*). L’argent de poche, une avance ou un
  remboursement arrivent sur son compte courant (`App\Money\TeenMoney`), notés comme dépense du foyer
  quand un compte du foyer est choisi. Quand un ado quitte la famille, ses comptes vides disparaissent, les
  autres reviennent au foyer, suffixés de son prénom.
- **Argent de poche** (`App\Money\AllowancePayer`) : montant chaque semaine ou chaque mois, à partir d’une date.
  Un versement mensuel garde son jour (le 31 tombe le 30 avril, puis le 31 mai). Chaque versement peut être
  pris sur un compte du foyer : il y est noté comme dépense *Argent de poche*.
- **Échéances** (`App\Money\Schedules`) : l’argent de poche et les opérations récurrentes dus sont enregistrés
  dès qu’un membre de la famille ouvre l’application, y compris ceux manqués depuis la dernière visite
  (verrou par famille contre les doublons), et chaque jour par la commande `app:schedules:run`. Les dates
  mensuelles, trimestrielles et annuelles gardent leur jour (`App\Enum\Frequency`).
- **Tirelire d’un enfant** : solde et historique. L’enfant note ses dépenses (jamais plus que ce qu’il a) ; un
  parent donne ou retire de l’argent (cadeau, correction).
- **Demandes** : un enfant ou un ado demande une **avance** (créditée tout de suite si un parent accepte, puis reprise sur
  les prochains versements d’argent de poche) ou un **achat** (les parents l’achètent s’ils sont d’accord). Le
  parent répond avec un mot facultatif ; l’enfant peut retirer une demande en attente. Un ado demande aussi le
  **remboursement** d’une dépense notée sur ses comptes (case à cocher en la notant, ou bouton sur la
  dépense) : le parent qui accepte choisit le compte du foyer et la catégorie ; la dépense est créditée sur
  le compte de l’ado et comptée dans le budget du foyer. Une dépense en attente de remboursement ne change
  plus ; une fois remboursée, seuls les parents la modifient.
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

Hors Docker, lancer au moins chaque jour : `php bin/console app:schedules:run` (cron). Dans l’image Docker, le
planificateur tourne toutes les heures.

## Installation (Docker)

Image tout-en-un `iemperor/tribubudget` : l’application et l’API (FrankenPHP), PostgreSQL 16 et le planificateur
des échéances, sans aucun réglage :

```bash
docker run -d --name tribubudget -p 8080:80 -v tribubudget:/data iemperor/tribubudget
docker logs tribubudget          # code d’installation, demandé sur http://<serveur>:8080
```

Au premier lancement, [`docker/entrypoint.sh`](docker/entrypoint.sh) crée dans le volume `/data` les secrets
(`/data/secrets` : `APP_SECRET`, `JWT_PASSPHRASE`, mot de passe de la base), la base (`/data/postgres`, écoutant
seulement en interne), les clés JWT et le code d’installation ; les variables d’environnement, si elles sont
données, priment. Avec `DATABASE_URL`, l’image utilise cette base au lieu de la sienne (installations 0.1.0).
Les processus tournent sous leur propre utilisateur (`postgres`, `www-data`), sous `tini` ; à l’arrêt, PostgreSQL
s’arrête proprement. Commandes : `docker exec tribubudget console …`. Fichiers : [`docker/compose.yaml`](docker/compose.yaml),
guide complet : [`docker/DOCKERHUB.md`](docker/DOCKERHUB.md) (page Docker Hub).

```bash
docker build --pull -t iemperor/tribubudget:X.Y.Z --build-arg APP_VERSION=X.Y.Z .
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
