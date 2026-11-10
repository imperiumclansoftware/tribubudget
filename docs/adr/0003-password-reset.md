# ADR 0003 — Réinitialisation sans e-mail, MFA reporté

- Statut : accepté
- Date : 2026-11-10
- Règles concernées : guidelines « Authentification et sessions » (`symfonycasts/reset-password-bundle`
  obligatoire ; MFA recommandé, obligatoire pour les administrateurs)

## Contexte

TribuBudget n’envoie aucun e-mail : une famille l’installe sans serveur SMTP, et ses membres (les enfants
surtout) n’ont pas forcément d’adresse. Une réinitialisation par lien e-mail n’a donc personne à qui écrire.

## Décision

- **Dans la famille**, un parent change le mot de passe ou le code de n’importe quel autre membre depuis
  l’application (*Famille*) ; c’est tracé dans le journal de sécurité.
- **Le dernier parent qui a oublié son mot de passe** : l’administrateur du serveur lance
  `docker exec -it tribubudget console app:user:reset-password <identifiant>`. Le mot de passe est saisi
  masqué, deux fois, jamais en argument (historique du shell), avec les mêmes règles que l’application ;
  le changement est journalisé et ferme les autres sessions du membre.
- **Pas de MFA pour l’instant.** Il n’y a pas de compte administrateur dans l’application (l’administration
  se fait sur le serveur), et un second facteur pour une famille est une décision produit à part. À
  reprendre avec `scheb/2fa-bundle` (TOTP) pour les parents, dans un ADR dédié.

## Conséquences

- Pas de jeton de réinitialisation à stocker, donc rien à hacher ni à faire expirer.
- Accéder au serveur permet de reprendre la main sur un compte : c’est déjà le cas (base de données).
