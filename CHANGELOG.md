# Journal des versions

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
