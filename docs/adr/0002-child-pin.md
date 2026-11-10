# ADR 0002 — Mots de passe de 12 caractères, code PIN pour les enfants

- Statut : accepté
- Date : 2026-11-10
- Règle concernée : guidelines « Authentification et sessions » (12 caractères minimum, mots de passe
  compromis refusés)

## Contexte

Les guidelines imposent 12 caractères au moins, `PasswordStrength` et `NotCompromisedPassword`. TribuBudget
avait 8 caractères pour les parents et les ados, et un code de 4 à 12 chiffres pour les enfants, qui ne
savent pas toujours lire ni retenir un mot de passe.

## Décision

- **Parents et ados** : 12 caractères au moins, `PasswordStrength` (score « moyen ») et
  `NotCompromisedPassword` (Have I Been Pwned, k-anonymat : seuls 5 caractères du SHA-1 quittent le
  serveur). La vérification en ligne est ignorée quand le serveur n’a pas accès à Internet
  (`skipOnError`) : un réseau familial sans sortie ne doit pas bloquer la création d’un compte.
- **Enfants : exception.** Ils gardent un code de 4 à 12 chiffres. Ce qui compense :
  - `login_throttling` : 5 essais par 15 minutes par identifiant et par IP, puis 429 ;
  - le compte d’un enfant ne donne accès qu’à sa propre tirelire et à ses demandes (`ChildVoter`) : il ne
    peut ni voir le budget du foyer, ni déplacer d’argent, seulement noter ses dépenses et demander ;
  - un parent change le code à tout moment ;
  - chaque échec de connexion est journalisé (canal `audit`).
- Les mots de passe existants restent valables : la règle s’applique à la création et au changement.

## Conséquences

- Les messages et les formulaires indiquent 12 caractères (`PasswordPolicy`, `FamilyRole::minPasswordLength()`).
- À revoir si l’enfant obtient un jour des droits sur de l’argent du foyer.
