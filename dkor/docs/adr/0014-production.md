# ADR 0014 — Mise en production : hébergement, base de données et accès de partout

- **Statut :** Accepté
- **Date :** 2026-10-25
- **Issue liée :** #107
- **Règle :** les questions ouvertes de l'[ADR 0006](0006-database.md) (moteur de production, `enforceMorphMap()`,
  regroupement des migrations) et de l'[ADR 0007](0007-reference-data-seeders.md) (script de déploiement,
  réalignement des permissions)

## Contexte

L'application ne roule que sur Laravel Herd, en local. La base est rebâtie par `migrate:fresh --seed` à chaque
changement de schéma ([ADR 0012](0012-inventory-by-store.md)) : aucune donnée réelle n'est conservée. Pour s'en servir
au quotidien, les employés doivent y accéder de partout, sur un ordinateur ou un téléphone :

- le **livreur** consulte ses livraisons et fait signer le client sur son téléphone ;
- la **designer** prépare un devis chez le client et le lui envoie par courriel ;
- la **comptabilité** travaille en télétravail ;
- la **propriétaire**, en voyage, consulte ses menus pour faire ses suivis.

L'application devient donc un système fermé ([ADR 0002](0002-auth.md)) joignable par Internet. Les données sont
surtout historiques (commandes, paiements, factures, journal d'inventaire, paie) et contiennent des renseignements
personnels sur les clients et les employés : elles ne doivent ni se perdre ni fuir.

État relevé dans le code au moment de la décision :

- `.env.example` : `APP_ENV=local`, `APP_DEBUG=true`, `MAIL_MAILER=log`, `FILESYSTEM_DISK=local`,
  `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=database`.
- Fortify n'a aucune fonction activée : pas de 2FA. La connexion est déjà limitée à 5 tentatives par minute par nom
  d'usager et adresse IP (`FortifyServiceProvider`).
- Six tâches planifiées (`routes/console.php`) sont écrites en heure locale (`dailyAt('05:00')`), mais
  `app.timezone` vaut `UTC` : en production, elles rouleraient 4 ou 5 heures trop tôt.
- Les signatures de livraison vont sur le disque `local` (`DELIVERY_SIGNATURE_DISK`).
- 55 appels à `lockForUpdate()`, sans effet en SQLite (ADR 0006).
- Le SQL brut (`whereRaw`, `orderByRaw`, `selectRaw`) n'utilise que des expressions standard (`LOWER()`,
  arithmétique, `is null`, `case when`).
- Les anciennes migrations de données exécutent les seeders **actuels** : `migrate_user_roles_to_permission_tables`
  crée les rôles par `RoleSeeder`, puis une trentaine de migrations accordent chaque permission à tous les rôles
  (ADR 0007). Sur SQLite, le dump les marque comme passées. Sur un autre moteur, il n'y a pas de dump : elles
  s'exécuteraient et redonneraient la matrice élargie (un vendeur pourrait supprimer des factures).
- Les références polymorphes stockent le nom de la classe (`inventory_movements.reference_type`,
  `ledger_entries`, `notifications.notifiable_type`, `model_has_roles.model_type`).
- La CI (`.github/workflows/laravel-ci.yml`, à la racine du dépôt) lance les tests ; aucun déploiement n'est automatisé.

## Options considérées

### Option A — Laravel Cloud ✔️

Plateforme gérée par Laravel : l'application est construite en image à chaque déploiement, avec base de données,
stockage objet, worker et planificateur rattachés à chaque environnement.

- ✅ Rien à administrer : système, PHP, HTTPS et mises à jour de sécurité sont gérés
- ✅ Environnements séparés (production, préproduction) avec leurs propres ressources
- ✅ Déploiement sans interruption, commandes de déploiement intégrées, domaine et certificat automatiques
- ✅ Base PostgreSQL ou MySQL gérée, stockage objet (Cloudflare R2), planificateur et worker en option
- ✅ Prévu par les guides du projet (skill `deploying-to-cloud`, CLI `laravel/cloud-cli`)
- ❌ Disque éphémère et non partagé : SQLite, fichiers locaux et sessions sur disque sont exclus
- ❌ Coût mensuel qui croît avec les ressources ; dépendance envers un fournisseur

### Option B — VPS géré (Forge, Ploi)

Un serveur loué (DigitalOcean, Hetzner, AWS…), configuré et déployé par un outil de gestion.

- ✅ Moins cher à ressources égales ; disque permanent (SQLite possible)
- ✅ Contrôle complet du serveur
- ❌ Système, PHP, pare-feu et sauvegardes à surveiller nous-mêmes
- ❌ Un seul serveur : une panne arrête tout, sans relève

### Option C — Serveur au magasin et réseau privé (Tailscale, VPN)

Un ordinateur au bureau chef, joignable seulement par un réseau privé.

- ✅ Les données restent dans l'entreprise ; aucun port ouvert sur Internet
- ❌ Chaque téléphone (livreur, designer) doit installer et garder actif le client du réseau privé
- ❌ Une panne de courant ou d'Internet au magasin coupe tous les employés sur la route
- ❌ Sauvegardes hors site, matériel et sécurité physique à notre charge

## Décision

Nous retenons l'**option A, Laravel Cloud**.

### Hébergement et environnements

- Une application Laravel Cloud, branchée sur le dépôt `sebastienmarley/dkorpos`, répertoire racine `dkor`.
- Deux environnements, chacun avec sa base, son stockage et ses variables :
  - **préproduction** : sert à valider chaque livraison et à essayer les restaurations ;
  - **production** : sous un sous-domaine de l'entreprise, HTTPS obligatoire.
- **Région :** Laravel Cloud n'offre pas PostgreSQL au Canada. La base de production est donc aux **États-Unis
  (Virginie, `aws-us-east-1`)**. Les renseignements personnels des clients et des employés sont hébergés hors du
  Québec : voir la question ouverte « Région et loi 25 ».
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_LOCALE=fr`, `APP_URL` en `https://`.

### Fuseau horaire : `America/Toronto`

- `app.timezone` passe de `UTC` à `America/Toronto` (`config/app.php`), dans tous les environnements, y compris le
  développement et les tests.
- Les dates sont enregistrées et affichées à l'heure du magasin. Les dates d'affaires (journée de caisse, date d'un
  comptage, semaine d'horaire, échéances) tombent le bon jour sans conversion, et les tâches planifiées roulent à
  l'heure prévue.
- Le changement se fait avant le premier déploiement : aucune date déjà enregistrée en UTC n'est à convertir.

### Exposition : Internet public, en HTTPS

L'application est joignable sur Internet, sans réseau privé. Les livreurs et la designer se connectent depuis leur
téléphone, sans rien installer. La protection repose sur l'authentification renforcée ci-dessous. Un accès filtré
en amont (Cloudflare Access) reste possible plus tard sans toucher au code.

### Base de données de production : PostgreSQL

- **PostgreSQL**, géré par Laravel Cloud, **sans mise en veille** en production, en Virginie (`aws-us-east-1`).
- Pourquoi PostgreSQL plutôt que MySQL :
  - les migrations sont **transactionnelles** : une migration qui échoue au déploiement est annulée en entier, au
    lieu de laisser un schéma à moitié modifié ;
  - c'est le moteur le plus rigoureux (types, contraintes) de l'ADR 0006.
- Les verrous par ligne (`lockForUpdate()`) deviennent réels : plusieurs caisses et téléphones écrivent en même temps
  sans bloquer toute la base.
- Le développement et les tests restent sur SQLite (ADR 0006). Une **seconde tâche de CI** (`tests-pgsql` dans
  `laravel-ci.yml`, service PostgreSQL 18) exécute les commandes de déploiement puis la suite sur PostgreSQL, pour
  attraper ce que SQLite tolère. En local, la même suite roule sur PostgreSQL (DBngin) en passant les variables `DB_*`
  à `php artisan test --parallel --processes=4`.
- Ce que la suite a révélé sur PostgreSQL, et les règles qui en découlent (ajoutées à la portabilité de l'ADR 0006) :

  | Écart avec SQLite | Règle |
  |---|---|
  | `LIKE` respecte la casse | Rechercher avec `whereLike()` / `orWhereLike()`, traduits en `ilike` sur PostgreSQL |
  | Une colonne `time` est rendue avec les secondes (`17:00:00`) | Caster les heures avec `App\Casts\TimeOfDay`, qui garde `HH:MM` |
  | Comparer `''` à une colonne entière est une erreur | Ignorer un filtre vide (`filled()`) ou le caster (`(int)`) |
  | Sans `orderBy`, l'ordre des lignes change d'une requête à l'autre | Trier explicitement quand l'ordre compte (lignes d'une commande) |
  | Une erreur annule toute la transaction en cours | Isoler une écriture qui peut échouer dans `DB::transaction()` (point de sauvegarde) |
  | Les longueurs `varchar` et les `CHECK` des `enum()` sont appliquées | `text` pour un texte long ; chaînes simples plutôt qu'`enum()` |

### Accès et sécurité

- **2FA (Fortify, `Features::twoFactorAuthentication()` avec confirmation)** :
  - **obligatoire** pour les rôles administrateur, propriétaire et comptabilité, et pour tout usager qui a
    `stores.all` ([ADR 0013](0013-store-scope.md)). Tant qu'il ne l'a pas activée, l'usager est redirigé vers son
    activation ;
  - **offerte** aux autres usagers.
  - Les rôles visés sont dans `config/access.php` (`two_factor.roles`) ; `User::requiresTwoFactor()` y ajoute
    `stores.all`. Le middleware `EnsureTwoFactorIsEnabled` (groupe `web`) renvoie l'usager vers
    **Réglages > Authentification à deux facteurs** (`two-factor.edit`), y compris pour une action Livewire d'une page
    restée ouverte. `TWO_FACTOR_ENFORCED=false` lève l'obligation (tests, développement local).
  - La page demande le mot de passe (`password.confirm`). Pendant l'activation imposée, elle s'affiche sans la
    navigation de l'application, avec **Se déconnecter** et, une fois la 2FA confirmée, **Continuer**. Un usager pour
    qui la 2FA est obligatoire ne peut pas la désactiver.
  - Codes de récupération à la configuration. Comme pour le mot de passe (ADR 0002), seul un superviseur
    réinitialise la 2FA d'un usager qui a perdu son téléphone, depuis sa fiche (même autorisation que le mot de
    passe). L'usager la réactive à sa connexion suivante.
- **Limite de tentatives** : on garde celle de Fortify (5 par minute par nom d'usager et IP, et 5 par minute pour le
  code 2FA).
- **Sessions** : en base (`SESSION_DRIVER=database`), `SESSION_SECURE_COOKIE=true`, durée de 120 minutes
  d'inactivité.
- **Usagers désactivés** (`is_active`) : la connexion les refuse (`Fortify::authenticateUsing()`, même message qu'un
  mauvais mot de passe), et le middleware `EnsureUserIsActive` déconnecte à sa requête suivante un usager désactivé
  pendant que sa session était ouverte.
- **HTTPS forcé** en production (`URL::forceHttps()`).

### Courriel

- **Aucun courriel ne part à un vrai destinataire** tant que ce n'est pas décidé explicitement. L'application écrit
  déjà aux fournisseurs (commande envoyée, demande d'annulation, écart de réception) : avec de vraies données, une
  commande partirait chez un vrai fournisseur.
  - **Redirection (`MAIL_ALWAYS_TO`)** : l'adresse globale `to` de `config/mail.php` fait appeler `alwaysTo()` par
    Laravel. Chaque courriel, avis compris, part à cette seule boîte, sans copie ni copie cachée.
  - **Garde** : sans adresse de redirection, un service d'envoi réel (`postmark`, `smtp`…) n'envoie rien et le
    journal l'indique (`AppServiceProvider::guardRealRecipients()`). Envoyer aux vrais destinataires demande
    `MAIL_ALLOW_REAL_RECIPIENTS=true`. Les mailers `log` et `array` ne sont pas touchés.
- **Pour l'instant, `MAIL_MAILER=log`** partout : aucun service d'envoi n'est configuré.
- **Postmark** est prévu pour la suite, par le mailer `postmark` de Laravel, mais pas encore installé :
  - le bloc est préparé en commentaire dans `.env.example` (`MAIL_MAILER=postmark`, `POSTMARK_API_KEY`,
    `POSTMARK_MESSAGE_STREAM_ID`) ;
  - à la création du compte : `composer require symfony/postmark-mailer symfony/http-client`, puis
    `MAIL_ALWAYS_TO` réglé avant `MAIL_MAILER=postmark`.
  - Alternatives écartées pour l'instant : Amazon SES (région Canada, mise en place plus lourde), Resend, Mailgun,
    SMTP de Microsoft 365 ou Google Workspace (limites d'envoi, aucun suivi des rebonds).
- Expéditeur sur le domaine de l'entreprise, avec SPF, DKIM et DMARC configurés, le jour où l'envoi réel est activé.

### Fichiers

- **Laravel Cloud Object Storage** (Cloudflare R2) : un compartiment **privé**, disque par défaut de l'environnement.
  Ajoute `league/flysystem-aws-s3-v3` (à approuver).
- `DELIVERY_SIGNATURE_DISK` pointe vers ce disque. Les signatures restent servies par
  `DeliverySignatureController`, après vérification des droits ; aucun fichier n'est public.

### Files d'attente et planificateur

- On garde `QUEUE_CONNECTION=database`, avec un **worker** (`queue:work`) rattaché à l'environnement. Pas de nouvelle
  dépendance ; les files gérées de Laravel Cloud seront réévaluées si le volume l'exige.
- Le **planificateur** de Laravel Cloud lance `schedule:run` chaque minute.
- Les tâches suivent `app.timezone` (`America/Toronto`, voir plus bas) : `dailyAt('05:00')` veut dire 5 h, heure du
  magasin. Pas besoin de `app.schedule_timezone`.
- Les tâches qui ne doivent rouler qu'une fois reçoivent `onOneServer()` si l'environnement a plusieurs instances.

### Déploiement

- **Branches :**
  - les branches de travail (une par issue) sont fusionnées dans **`develop`** par une PR ;
  - **`develop`** se déploie en **préproduction** ;
  - **`main`** se déploie en **production**. Elle ne reçoit que des fusions de `develop`, par une PR, une fois la
    préproduction validée.
- Le déploiement automatique à chaque poussée de Laravel Cloud est **désactivé**. C'est la CI qui déploie : quand
  les tests passent sur `develop`, elle appelle le crochet de déploiement de la préproduction ; quand ils passent
  sur `main`, celui de la production. Un commit dont les tests échouent n'est jamais déployé.
- La CI roule aussi sur chaque PR vers `develop` et `main`.
- **Construction :** `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`,
  `php artisan optimize`.
- **Déploiement**, dans l'ordre :

  ```bash
  php artisan migrate --force
  php artisan db:seed --class=ReferenceDataSeeder --force
  ```

  Cela règle la question ouverte de l'ADR 0007 : la seconde commande ne peut plus être oubliée.

### Passage à des données permanentes

Avant le **premier** déploiement en production, une seule fois :

1. **Migrations de départ (fait le 2026-10-25).** Les 179 migrations sont remplacées par **79 migrations de
   structure seulement, une par table** (`2026_10_25_000001_…` à `…000079_…`), dans l'ordre des clés étrangères.
   - Elles ont été générées à partir d'une base PostgreSQL bâtie par les anciennes migrations, puis comparées
     colonne par colonne (type, nullable, défaut), index par index et clé étrangère par clé étrangère, sur PostgreSQL
     et sur SQLite : le schéma est identique, sauf les trois corrections ci-dessous.
   - Les migrations de données (rôles, permissions, conversions) disparaissent : ce sont les seeders qui s'en
     chargent (ADR 0007). C'est permis parce qu'aucune base à conserver ne les avait encore exécutées.
   - On n'utilise pas `schema:dump --prune` : le dump est propre au moteur et ne servirait pas à PostgreSQL. Le dump
     SQLite (`sqlite-schema.sql`) est refait à partir des nouvelles migrations.
   - **Trois corrections de schéma**, révélées par PostgreSQL :
     - `permissions.description` passe en `text` : la description de `stores.all` dépasse 255 caractères, et
       `ReferenceDataSeeder` aurait échoué au premier déploiement ;
     - `suppliers.type` et `stores.type` deviennent des chaînes simples : les anciennes migrations laissaient sur
       PostgreSQL une contrainte `CHECK` qui n'acceptait que `product` et `service`, et refusait donc les
       fournisseurs `shipping`.
2. **`Relation::enforceMorphMap()`** dans `AppServiceProvider` (`configureMorphMap()`) : chacun des 65 modèles de
   `app/Models` a un alias stable, son nom en minuscules séparées par des `_` (`user`, `reception_line`,
   `inventory_adjustment`…). Le journal d'inventaire, les écritures, les avis et les rôles enregistrent cet alias au
   lieu du nom de la classe : renommer un modèle ne brise plus les données. Un alias ne change plus une fois en
   production, et tout nouveau modèle reçoit le sien (`MorphMapTest` échoue sinon).
3. **Permissions** : la production part d'une base neuve, qui reçoit exactement `config/access.php`. Il n'y a rien à
   réaligner (question de l'ADR 0007, sans objet en production).
4. **Premier administrateur** : `php artisan app:create-admin`, lancé une fois après le premier déploiement.
   - **Dans un terminal**, elle demande le prénom, le nom, le magasin d'appartenance et le mot de passe, sans écho :
     il n'apparaît ni à l'écran ni dans l'historique du terminal.
   - **Sans terminal interactif** (exécuteur de commandes de Laravel Cloud, `-n`), les options sont obligatoires et
     le mot de passe est généré, puis affiché une seule fois :

     ```bash
     php artisan app:create-admin --firstname=Marie --lastname=Gagnon --store="Bureau chef"
     ```

     La sortie reste dans l'historique des commandes de Laravel Cloud : un autre administrateur ou le propriétaire
     réinitialise ce mot de passe après la première connexion. `--generate-password` force ce mode dans un terminal.
   - Le mot de passe suit `Password::defaults()` (12 caractères, casse mixte, chiffres, symboles et absent des
     fuites connues en production). Un mot de passe généré qui ne la respecte pas est regénéré.
   - `--store` désigne un magasin actif par son nom. Si aucun magasin n'existe, elle crée le premier, comme magasin
     physique ; un nom inconnu alors que des magasins existent est refusé.
   - Elle refuse de rouler si le rôle `admin` n'existe pas (seeder pas encore exécuté) ou si un administrateur
     existe déjà ; tout se fait dans une transaction.
   - Aucune donnée de démo : seul `ReferenceDataSeeder` roule. Les autres comptes, dont le propriétaire, se créent
     ensuite dans l'application (un rôle de même niveau peut s'attribuer).

Après ce premier déploiement, la règle de l'ADR 0006 s'applique sans exception : une migration fusionnée dans
`develop` ne se modifie plus (la préproduction l'a déjà exécutée), et chaque changement de schéma est une nouvelle
migration. `migrate:fresh` ne sert plus qu'en local ; `DB::prohibitDestructiveCommands()` l'interdit en production.

### Sauvegardes et restauration

- Sauvegardes automatiques de la base gérée par Laravel Cloud, avec la rétention la plus longue offerte par le forfait
  (au moins 7 jours).
- Une **restauration est essayée en préproduction** avant la mise en production, puis chaque trimestre, et avant
  toute migration qui transforme des données.
- Les signatures du stockage objet ne sont pas sauvegardées à part : elles sont temporaires et purgées
  (`PurgeDeliverySignatures`).

### Surveillance

- Journaux de Laravel Cloud pour chaque environnement (`LOG_LEVEL=warning` en production).
- Alertes d'erreurs par **Laravel Nightwatch** (paquet `laravel/nightwatch`, à approuver).
- En production, l'usager ne voit qu'une page d'erreur générique (`APP_DEBUG=false`).

## Conséquences

**Positives**

- Les employés travaillent de partout, sur leur téléphone, sans rien installer.
- Aucun serveur à administrer ; HTTPS, mises à jour et déploiements sans interruption sont gérés.
- Les verrous par ligne protègent enfin les écritures concurrentes, et une migration ratée s'annule d'elle-même.
- Un déploiement fait toujours les deux mêmes commandes, après des tests verts.
- La production démarre avec la matrice de permissions de la config, sans l'héritage des anciennes migrations.
- Les dates et les tâches planifiées sont à l'heure du magasin, sans conversion.

**Négatives / limitations**

- Les tests locaux restent sur SQLite : une différence de moteur n'apparaît qu'à la tâche de CI PostgreSQL.
- **Heure locale enregistrée** : au passage à l'heure normale (novembre), l'heure de 1 h à 2 h du matin revient deux
  fois. Deux événements de cette heure-là peuvent sembler mal ordonnés ; le magasin est alors fermé. Un magasin dans
  un autre fuseau demanderait de revoir cette décision.
- Coût mensuel récurrent (calcul, base, stockage, Postmark, Nightwatch).
- Quatre nouveaux paquets à approuver (Postmark et http-client à la création du compte Postmark, S3, Nightwatch).
- Exposée sur Internet, l'application dépend de mots de passe solides et de la 2FA des comptes sensibles.
- Les usagers sans 2FA obligatoire (vendeurs, livreurs) n'ont que leur mot de passe.
- Le remplacement des migrations et la carte des alias sont des changements faits une seule fois : chaque base de
  développement doit être rebâtie (`migrate:fresh --seed`).
- Les relations `hasMany` n'ont pas d'ordre par défaut : sur PostgreSQL, une liste dont l'ordre compte doit être
  triée explicitement. Seules les lignes de commande client ont été vérifiées.

**Protections mises en place**

- `APP_DEBUG=false`, HTTPS forcé, cookies de session sécurisés.
- Courriels redirigés vers `MAIL_ALWAYS_TO`, ou bloqués sans elle (`MailRedirectionTest`).
- 2FA obligatoire pour les comptes qui voient tous les magasins ou l'argent ; limite de tentatives sur la connexion
  et le code.
- `DB::prohibitDestructiveCommands()` en production : `migrate:fresh`, `db:wipe` et `migrate:reset` refusent de
  rouler.
- `enforceMorphMap()` lance une exception si un modèle sans alias est enregistré dans une référence polymorphe.
- Déploiement seulement après des tests verts, en préproduction (`develop`) avant la production (`main`).
- Restaurations essayées, pas seulement sauvegardes prises.
- La suite roule sur SQLite et sur PostgreSQL dans la CI.
- Tests Pest : `TwoFactorAuthenticationTest` (2FA obligatoire selon le rôle et `stores.all`, activation, défi à la
  connexion, page sans navigation), réinitialisation de la 2FA dans `Users/ShowTest`, usagers désactivés dans
  `AuthenticationTest`, `CreateAdminCommandTest`, `MorphMapTest`, `ScheduledTasksTimezoneTest`.

## Questions ouvertes

- **Région et loi 25** : la base PostgreSQL est en Virginie, faute de région canadienne pour PostgreSQL chez Laravel
  Cloud. Avant le lancement, faire l'évaluation des facteurs relatifs à la vie privée exigée par la loi 25 pour des
  données hébergées hors du Québec. **Si l'hébergement aux États-Unis est un enjeu, il faut passer à MySQL, offert
  dans une région canadienne, avant le lancement**, tant que la base ne contient aucune donnée réelle. Ce passage
  demande :
  - une tâche de CI sur MySQL à la place de `tests-pgsql`, et les écarts qu'elle révélera (comparaisons de texte
    insensibles à la casse et `unique` qui ne distingue plus les majuscules, `GROUP BY` plus permissif, migrations
    non transactionnelles) ;
  - de revoir cette décision et la section « Base de données de production ».

  Les migrations de départ sont écrites avec le schéma de Laravel, sans SQL propre à PostgreSQL : elles devraient
  s'appliquer à MySQL telles quelles, mais ce n'est pas encore vérifié.
- **2FA pour tous** : l'étendre aux vendeurs et livreurs si un incident ou l'assurance l'exige.
- **Copie hors fournisseur** : un export chiffré de la base, gardé ailleurs que chez Laravel Cloud ?
- **PWA et mode hors-ligne** pour les livreurs en zone sans réseau.
- **Impression portative** des devis et reçus.
- **Import des données** de l'ancien système (produits, clients, historique) : issue distincte.
- **Coûts** à réévaluer après trois mois d'utilisation.

## Références

- `.env.example`, `config/app.php` (`timezone`), `config/fortify.php`, `config/session.php`,
  `config/filesystems.php`, `config/mail.php`, `config/deliveries.php`
- `config/access.php` (`two_factor`), `phpunit.xml` (`TWO_FACTOR_ENFORCED=false`)
- `app/Providers/FortifyServiceProvider.php`, `app/Providers/AppServiceProvider.php` (`configureMorphMap()`)
- `app/Http/Middleware/EnsureUserIsActive.php`, `app/Http/Middleware/EnsureTwoFactorIsEnabled.php`, `bootstrap/app.php`
- `app/Livewire/Settings/TwoFactor.php`, `resources/views/livewire/settings/two-factor.blade.php`,
  `resources/views/livewire/auth/two-factor-challenge.blade.php`, `app/Livewire/Users/Show.php` (`resetTwoFactor()`)
- `app/Console/Commands/CreateAdmin.php`, `app/Casts/TimeOfDay.php`
- `routes/console.php`, `.github/workflows/laravel-ci.yml` (racine du dépôt, tâches `tests` et `tests-pgsql`)
- `database/migrations/` (79 migrations de départ), `database/schema/sqlite-schema.sql`,
  `database/seeders/ReferenceDataSeeder.php`
- [ADR 0002](0002-auth.md), [ADR 0006](0006-database.md), [ADR 0007](0007-reference-data-seeders.md),
  [ADR 0012](0012-inventory-by-store.md), [ADR 0013](0013-store-scope.md)
- Laravel Cloud : https://cloud.laravel.com/docs
- `.cursor/skills/deploying-to-cloud/SKILL.md`
