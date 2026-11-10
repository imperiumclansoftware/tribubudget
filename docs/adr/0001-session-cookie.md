# ADR 0001 — Cookie de session plutôt que JWT

- Statut : accepté
- Date : 2026-11-10
- Règles concernées : guidelines « Authentification et sessions », « Angular », « En-têtes HTTP, CORS et cookies »

## Contexte

Jusqu’à la 0.5, l’application Angular s’authentifiait avec un JWT (LexikJWT, 7 jours) gardé dans le
`localStorage` du navigateur : exposé à la moindre faille XSS, et impossible à révoquer avant son expiration
(changement de mot de passe, déconnexion). Les guidelines de l’équipe l’interdisent : une SPA servie sur le
même site que son API s’authentifie par cookie de session.

## Décision

- **Session Symfony** (`json_login`, pare-feu `main`), cookie `TRIBUBUDGET_SID` `HttpOnly`, `SameSite=Lax`,
  valable 7 jours comme l’ancien JWT. Les sessions sont stockées dans PostgreSQL (`PdoSessionHandler`,
  table `sessions`) : une mise à jour ou un redémarrage du conteneur ne déconnecte personne.
- **CSRF « double submit »** : cookie `XSRF-TOKEN` (SameSite=Strict, lisible par Angular) recopié dans
  l’en-tête `X-XSRF-TOKEN` par `HttpClient` (`withXsrfConfiguration`) ; `CsrfDoubleSubmitSubscriber` le
  vérifie sur toute requête `/api` qui modifie des données, connexion comprise.
- **Déconnexion** : `POST /api/logout` ferme la session côté serveur. Un changement de mot de passe ferme
  les autres sessions du membre (la session garde une empreinte CRC32C du hachage, `User::__serialize()`).
- **Échec de connexion** : même message et même code (401) que l’identifiant existe ou non ; 429 après
  5 essais en 15 minutes (`login_throttling`).

### Écart assumé : le HTTP sur le réseau de la maison

TribuBudget est auto-hébergé par des familles, souvent sur leur réseau local, en HTTP ; le HTTPS est fourni
par un reverse proxy quand l’application est ouverte sur Internet (`docker/DOCKERHUB.md`). Imposer `Secure`
empêcherait toute connexion en HTTP. Donc, par rapport au squelette :

| Squelette | TribuBudget | Pourquoi |
| --- | --- | --- |
| Cookie `__Host-SID`, `Secure` forcé en prod | `TRIBUBUDGET_SID`, `cookie_secure: auto` | Le préfixe `__Host-` exige `Secure` |
| HSTS et redirection HTTPS par nelmio (`forced_ssl`) | HSTS envoyé par nginx seulement si la requête est arrivée en HTTPS | Ne pas bloquer le HTTP local |

Le cookie est `Secure` dès que la requête est en HTTPS, directement ou via un reverse proxy de confiance
(`TRUSTED_PROXIES`, `private_ranges` par défaut dans l’image). La recommandation reste le HTTPS dès que
l’application sort de la maison.

## Conséquences

- Plus aucun jeton manipulé par le code Angular, plus rien dans `localStorage` (règle ESLint).
- LexikJWT, ses clés (`/data/jwt`) et `JWT_PASSPHRASE` disparaissent ; à la mise à jour, chaque membre se
  reconnecte une fois.
- Un futur client mobile ou tiers demandera un ADR dédié (JWT de 15 minutes et refresh token rotatif).
