# ADR 0015 — Verrouillage de la session par NIP après inactivité

- **Statut :** Accepté
- **Date :** 2026-10-26 (révisée le 2026-10-31 : état gardé dans le cache plutôt qu'en session)
- **Issue liée :** #122
- **Complète :** [ADR 0002](0002-auth.md) (limitation d'une session laissée ouverte) et [ADR 0014](0014-production.md)
  (création imposée à la connexion, sur le modèle de la 2FA)

## Contexte

Les postes du magasin sont partagés entre employés. Une session laissée ouverte permet à n'importe qui d'agir sous
l'identité de l'usager connecté. Le personnel de bureau, lui, laisse souvent une page ouverte longtemps sans
l'utiliser : une expiration courte de la session lui ferait perdre son travail et l'obligerait à se reconnecter avec
son mot de passe et sa 2FA plusieurs fois par jour.

Il faut aussi qu'une session verrouillée le reste partout : ouvrir un nouvel onglet ou recharger la page ne doit pas
donner accès à l'application au nom de l'usager.

## Options considérées

### Option A — Expiration courte de la session

Réduire `SESSION_LIFETIME` à quelques minutes.

- ✅ Aucun développement
- ❌ L'usager perd le contenu des formulaires et l'état des pages
- ❌ Reconnexion complète (mot de passe et 2FA) après chaque pause

### Option B — Verrouillage côté navigateur seulement

Un écran superposé à la page après quelques minutes d'inactivité.

- ✅ La page garde son état
- ❌ Contourné en retirant l'écran avec les outils du navigateur, ou en ouvrant un nouvel onglet

### Option C — Verrouillage gardé côté serveur, déverrouillage par NIP ✔️

Le serveur garde l'état verrouillé et refuse toute requête tant que le NIP n'est pas saisi. Le
navigateur affiche un écran superposé à la page pour que celle-ci garde son état.

- ✅ La page et les formulaires gardent leur état ; l'usager reprend en quelques secondes
- ✅ Un nouvel onglet ou une page rechargée tombe sur l'écran de verrouillage
- ✅ « Changer d'utilisateur » libère le poste pour un collègue
- ❌ Un NIP de 4 à 6 chiffres est plus faible qu'un mot de passe (compensé par la limite d'essais)
- ❌ Chaque usager doit créer un NIP

## Décision

Nous retenons l'**option C**.

**NIP**

- Colonne `users.pin`, hachée (cast `hashed`) et cachée de la sérialisation.
- Obligatoire pour tous. `EnsurePinIsSet` renvoie vers `pin.edit` l'usager qui n'en a pas. Il est placé après
  `EnsureTwoFactorIsEnabled` : la 2FA obligatoire est activée en premier, puis le NIP est créé.
- Créé juste après la connexion sans redemander le mot de passe. Le changer depuis Paramètres › NIP exige le mot de
  passe actuel (trait `ConfirmsCurrentPassword`, limité à 5 essais par minute).
- `access.pin.enforced` permet de désactiver l'obligation dans les tests qui n'en traitent pas.

**Verrouillage**

- `App\Support\SessionLock` garde la dernière activité et l'état verrouillé dans le cache, sous un jeton aléatoire
  rangé dans la session (`session_lock.token`), pour la durée de la session (`session.lifetime`). Ils ne sont pas dans
  la session elle-même : chaque requête réécrit la session entière telle qu'elle l'a lue, si bien qu'une requête
  Livewire en cours (un `wire:poll`, par exemple) effaçait un battement de cœur, un verrouillage ou un déverrouillage
  enregistré pendant ce temps. Le jeton, lui, ne change jamais : une copie périmée de la session le réécrit à
  l'identique.
- Une session sans jeton est neuve et démarre son activité. Une session qui a un jeton mais plus d'état dans le cache
  (cache vidé au déploiement, par exemple) est verrouillée plutôt que rouverte.
- `EnsureSessionIsUnlocked` verrouille la session inactive depuis `access.session_lock.idle_minutes` (5 par défaut,
  `SESSION_LOCK_IDLE_MINUTES`). Une session verrouillée reçoit une redirection vers l'écran de verrouillage pour une
  page, et un 423 pour une requête Livewire ou JSON. Seuls l'écran de verrouillage (`session-lock.*`) et la
  déconnexion restent accessibles.
- Seules les pages comptent comme activité côté serveur : un sondage `wire:poll` ne garde pas la session ouverte.
  Le navigateur signale l'activité sur une page déjà chargée (souris, clavier, toucher) par un battement de cœur
  (`session-lock.heartbeat`), au plus toutes les 30 secondes.
- Dans le navigateur (`resources/js/app.js`), l'inactivité est partagée entre les onglets par le `localStorage` :
  travailler dans un onglet garde les autres ouverts, et verrouiller ou déverrouiller un onglet fait de même pour les
  autres. Un 423 reçu par Livewire affiche l'écran au lieu de la fenêtre d'erreur. Chaque requête refusée réaffiche
  l'écran par-dessus les modals ouverts entre-temps, sans effacer le NIP en cours de saisie.
- « Verrouiller » dans le menu de l'usager verrouille tout de suite, pour un poste partagé.

**Déverrouillage**

- Le bon NIP déverrouille la session ; l'écran pleine page renvoie à la page demandée.
- Les essais erronés sont comptés sur la durée de la session (`session.lifetime`), pour qu'on ne puisse pas deviner
  le NIP en espaçant les essais. Après `access.session_lock.max_attempts` (5) essais, l'usager est déconnecté et doit
  se reconnecter avec son mot de passe et sa 2FA.
- « Changer d'utilisateur » déconnecte l'usager et mène à la page de connexion.

## Conséquences

**Positives**

- Une session laissée ouverte sur un poste partagé n'est plus utilisable par un autre employé.
- Le personnel de bureau reprend son travail en quelques secondes, sans le perdre.

**Négatives / limitations**

- Les usagers existants doivent créer leur NIP à leur prochaine connexion.
- Le contenu déjà affiché reste dans la page, sous l'écran superposé : on peut le lire en retirant l'écran avec les
  outils du navigateur. Aucune action n'est possible, le serveur refusant les requêtes.
- Un usager qui oublie son NIP doit se reconnecter avec son mot de passe, puis le changer dans ses paramètres.
- Le verrou dépend du cache (`CACHE_STORE`) : chaque requête d'un usager qui a un NIP y lit son état. Vider le cache
  verrouille toutes les sessions ouvertes.

**Protections mises en place**

- Le verrou est appliqué par le serveur, pas par l'écran du navigateur.
- Une reconnexion par le témoin « Se souvenir de moi » arrive verrouillée : elle ne contourne pas le NIP.
- Le bouton Précédent ne réaffiche pas une page déverrouillée. Les pages sont envoyées avec `Cache-Control: no-store`,
  pour que le navigateur les redemande au serveur. Une page que `wire:navigate` ou le cache du navigateur (bfcache)
  restaure sans requête redemande l'état du verrou au serveur, et affiche l'écran sans attendre si la session a été
  verrouillée entre-temps.
- L'écran superposé est un `<dialog>` ouvert par `showModal()` : il passe par-dessus les modals Flux ouverts et rend le
  reste de la page inerte. Chrome laisse Échap le fermer ; il est rouvert tant que la session est verrouillée.
- Déverrouiller par NIP ne remplace pas la confirmation du mot de passe : changer son NIP ou régénérer son mot de passe
  l'exige toujours.

## Questions ouvertes

- Un délai d'inactivité différent par rôle ou par poste (bureau et plancher).
- La réinitialisation d'un NIP oublié par un superviseur, depuis la fiche usager.

## Références

- `database/migrations/2026_10_26_201403_add_pin_to_users_table.php`
- `app/Http/Middleware/EnsurePinIsSet.php`, `app/Http/Middleware/EnsureSessionIsUnlocked.php`
- `app/Support/SessionLock.php`, `app/Http/Controllers/SessionLockController.php`, `app/Livewire/Settings/Pin.php`
- `resources/views/partials/session-lock.blade.php`, `resources/js/app.js`
- `config/access.php` (`pin`, `session_lock`)
