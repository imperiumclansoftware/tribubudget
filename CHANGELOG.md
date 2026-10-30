# Journal des versions

## 0.4.0 — 2026-10-30

**Une image sans CVE connue.**

- Le serveur web passe de FrankenPHP à **nginx et PHP-FPM** (paquets Alpine et image PHP officielle). Le
  binaire FrankenPHP embarquait des bibliothèques Go signalées par les scanners sans correctif disponible
  (`go-jose` v3 : une CVE haute, deux moyennes), bien qu’inutilisées ici (HTTPS automatique désactivé).
  L’image ne contient plus aucun binaire Go : aucune CVE connue, toutes sévérités confondues.
- Rien ne change pour l’application ni pour les données. Les processus maîtres de nginx et PHP-FPM
  démarrent en root, leurs workers tournent en `www-data`.

Mise à jour depuis 0.3.x : rien à faire ; les dossiers `caddy` et `caddy-config` du volume `/data` ne
servent plus et peuvent être supprimés.

## 0.3.1 — 2026-10-30

**Correctifs de sécurité de l’image.**

- L’image applique les correctifs de sécurité d’Alpine publiés depuis l’image de base : `curl` 8.23.0
  (CVE-2026-92392, use-after-free lors d’un envoi HTTP/2).

## 0.3.0 — 2026-10-30

**Les ados et leurs comptes, le pointage et le reste prévu.**

- **Profil ado** : connexion par mot de passe ; un *Compte courant* et une *Épargne* à lui dès sa création,
  en dehors du budget du foyer (solde, chiffres du mois, budgets, projets), montrés à part au tableau de bord.
- L’ado note ses revenus et dépenses et vire entre ses comptes ; les parents voient tout, notent aussi des
  opérations, font des virements depuis ou vers le foyer et règlent son argent de poche, versé sur son
  compte courant.
- **Demandes de remboursement** : l’ado demande le remboursement d’une dépense ; accepté, le montant est
  crédité sur son compte et enregistré comme dépense du foyer dans la catégorie choisie. Les ados font aussi
  les demandes d’avance et d’achat.
- **Pointage** des opérations sur le relevé de la banque : une case par opération, et le solde pointé de
  chaque compte.
- **Reste prévu fin du mois** au tableau de bord : revenus reçus et à venir, moins budgets, récurrentes hors
  budgets, argent de poche à verser et autres dépenses du mois.
- *Comptes* : comptes regroupés par titulaire, champ *Titulaire* ; modifier un compte d’épargne garde son
  plancher (il était effacé).

Mise à jour depuis 0.2.0 : rien à faire, la base est mise à jour au démarrage du conteneur (comptes des ados,
demandes de remboursement, pointage).

## 0.2.0 — 2026-10-28

**Image tout-en-un et page d’installation.**

- **Image Docker tout-en-un** : l’application, sa base PostgreSQL 16 et le planificateur des échéances dans un
  seul conteneur, sans aucun réglage : `docker run -d --name tribubudget -p 8080:80 -v tribubudget:/data
  iemperor/tribubudget`. Au premier lancement, les secrets (`APP_SECRET`, `JWT_PASSPHRASE`, mot de passe de la
  base), la base et les clés de connexion sont créés dans le volume `/data`. Arrêt propre de la base.
- **Page d’installation** à la première visite : elle demande le code d’installation affiché dans les journaux du
  serveur (`docker logs tribubudget`), puis crée la famille et son premier compte parent.
- **Une instance = une famille** : l’inscription libre est supprimée ; les parents ajoutent les autres membres.
- Budgets : boutons créer et supprimer (aussi dans 0.1.0).

Mise à jour depuis 0.1.0 : la base séparée reste utilisable en gardant `DATABASE_URL` (et `APP_SECRET`,
`JWT_PASSPHRASE`) dans le compose ; le service `scheduler` devient facultatif.

## 0.1.0 — 2026-10-28

Première version de TribuBudget, le budget de toute la famille.

- **Deux profils** : les parents gèrent tout ; les enfants voient leur tirelire (argent de poche), notent leurs
  dépenses et font des demandes. Connexion des enfants par code, freinée après 5 essais ratés.
- **Comptes et opérations** : comptes courants, d’épargne et espèces ; revenus et dépenses par catégorie,
  filtrés par mois, compte et catégorie.
- **Virements** entre les comptes de la famille, sans fausser les revenus et les dépenses.
- **Opérations récurrentes** (loyer, abonnements, salaire) : hebdomadaires, mensuelles, trimestrielles ou
  annuelles, enregistrées seules à leur date, avec date de fin facultative.
- **Budgets mensuels** par catégorie, créés (au besoin avec une nouvelle catégorie) et supprimés librement,
  dépassements signalés.
- **Projets d’épargne** rattachés à un compte d’épargne, financés par ordre de priorité au-dessus d’un
  plancher ; les projets réalisables sont mis en avant.
- **Argent de poche** automatique (chaque semaine ou chaque mois), pris sur un compte du foyer ; avances
  remboursées sur les versements suivants.
- **Tableau de bord** : solde, chiffres du mois, budgets, six derniers mois, projets réalisables,
  prochaines échéances, tirelires des enfants.
- **Interface** mobile first, du téléphone à l’écran large, thème clair ou sombre selon le système.
- **Image Docker** : application et API servies par FrankenPHP, planificateur des échéances, PostgreSQL.
