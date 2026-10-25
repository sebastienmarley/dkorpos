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

État actuel relevé dans le code :

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
- Région au Canada, pour garder les renseignements personnels au pays.
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

- **PostgreSQL**, géré par Laravel Cloud, **sans mise en veille** en production.
- Pourquoi PostgreSQL plutôt que MySQL :
  - les migrations sont **transactionnelles** : une migration qui échoue au déploiement est annulée en entier, au
    lieu de laisser un schéma à moitié modifié ;
  - les comparaisons de texte sont sensibles à la casse, **comme en SQLite** : les tests et la production se
    comportent pareil (la recherche passe déjà par `search_name` et `LOWER()`) ;
  - c'est le moteur le plus rigoureux (types, contraintes) de l'ADR 0006.
- Les verrous par ligne (`lockForUpdate()`) deviennent réels : plusieurs caisses et téléphones écrivent en même temps
  sans bloquer toute la base.
- Le développement et les tests restent sur SQLite (ADR 0006). Une **seconde tâche de CI** exécute les migrations et
  la suite de tests sur PostgreSQL, pour attraper ce que SQLite tolère (types, `GROUP BY`, comparaisons de booléens).

### Accès et sécurité

- **2FA (Fortify, `Features::twoFactorAuthentication()` avec confirmation)** :
  - **obligatoire** pour les rôles administrateur, propriétaire et comptabilité, et pour tout usager qui a
    `stores.all` ([ADR 0013](0013-store-scope.md)). Tant qu'il ne l'a pas activée, l'usager est redirigé vers son
    activation ;
  - **offerte** aux autres usagers.
  - Codes de récupération à la configuration. Comme pour le mot de passe (ADR 0002), seul un superviseur
    réinitialise la 2FA d'un usager qui a perdu son téléphone.
- **Limite de tentatives** : on garde celle de Fortify (5 par minute par nom d'usager et IP, et 5 par minute pour le
  code 2FA).
- **Sessions** : en base (`SESSION_DRIVER=database`), `SESSION_SECURE_COOKIE=true`, durée de 120 minutes
  d'inactivité. Aujourd'hui, un usager désactivé (`is_active`) garde sa session ouverte : un middleware le
  déconnectera à sa prochaine requête.
- **HTTPS forcé** en production (`URL::forceHttps()`).

### Courriel

- **Postmark**, par le mailer `postmark` de Laravel. Ajoute les paquets `symfony/postmark-mailer` et
  `symfony/http-client` (changement de dépendances à approuver).
- Expéditeur sur le domaine de l'entreprise, avec SPF, DKIM et DMARC configurés.
- En préproduction, `MAIL_MAILER=log` : aucun courriel ne part vers un vrai client.

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

1. **Migrations de départ.** Les 178 migrations sont remplacées par des migrations de structure seulement, qui
   recréent le schéma actuel. Les migrations de données (rôles, permissions, `migrate_user_roles_to_permission_tables`)
   disparaissent : ce sont les seeders qui s'en chargent (ADR 0007). C'est permis parce qu'aucune base à conserver ne
   les a encore exécutées. On n'utilise pas `schema:dump --prune` : le dump est propre au moteur
   (`sqlite-schema.sql`) et ne servirait pas à PostgreSQL. Le dump SQLite est refait à partir des nouvelles
   migrations.
2. **`Relation::enforceMorphMap()`** dans `AppServiceProvider`, avec un alias pour chaque modèle référencé :
   sources du journal d'inventaire et des écritures, destinataires des avis (`User`), modèles des rôles de
   `spatie/laravel-permission`. Renommer un modèle ne brise plus les données.
3. **Permissions** : la production part d'une base neuve, qui reçoit exactement `config/access.php`. Il n'y a rien à
   réaligner (question de l'ADR 0007, sans objet en production).
4. **Premier administrateur** : une commande `app:create-admin` demande le nom, le magasin et le mot de passe,
   sans les écrire dans l'historique du terminal. Aucune donnée de démo : seul `ReferenceDataSeeder` roule.

Après ce premier déploiement, la règle de l'ADR 0006 s'applique sans exception : une migration fusionnée dans
`develop` ne se modifie plus (la préproduction l'a déjà exécutée), et chaque changement de schéma est une nouvelle migration. `migrate:fresh` ne sert plus qu'en local ;
`DB::prohibitDestructiveCommands()` l'interdit en production.

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
- Quatre nouveaux paquets à approuver (Postmark, http-client, S3, Nightwatch).
- Exposée sur Internet, l'application dépend de mots de passe solides et de la 2FA des comptes sensibles.
- Les usagers sans 2FA obligatoire (vendeurs, livreurs) n'ont que leur mot de passe.
- Le remplacement des migrations est un gros changement fait une seule fois : chaque base de développement doit être
  rebâtie (`migrate:fresh --seed`).

**Protections mises en place**

- `APP_DEBUG=false`, HTTPS forcé, cookies de session sécurisés.
- 2FA obligatoire pour les comptes qui voient tous les magasins ou l'argent ; limite de tentatives sur la connexion
  et le code.
- `DB::prohibitDestructiveCommands()` en production : `migrate:fresh`, `db:wipe` et `migrate:reset` refusent de
  rouler.
- `enforceMorphMap()` lance une exception si un modèle sans alias est enregistré dans une référence polymorphe.
- Déploiement seulement après des tests verts, en préproduction (`develop`) avant la production (`main`).
- Restaurations essayées, pas seulement sauvegardes prises.
- Tests Pest : 2FA obligatoire selon le rôle et `stores.all`, `app:create-admin`, `enforceMorphMap`, seeders relancés
  sur une base à jour, fuseau des tâches planifiées.

## Questions ouvertes

- **Région et loi 25** : confirmer qu'une région canadienne est offerte par Laravel Cloud. Si les données sont
  hébergées hors du Québec, faire l'évaluation des facteurs relatifs à la vie privée exigée par la loi 25.
- **2FA pour tous** : l'étendre aux vendeurs et livreurs si un incident ou l'assurance l'exige.
- **Copie hors fournisseur** : un export chiffré de la base, gardé ailleurs que chez Laravel Cloud ?
- **PWA et mode hors-ligne** pour les livreurs en zone sans réseau.
- **Impression portative** des devis et reçus.
- **Import des données** de l'ancien système (produits, clients, historique) : issue distincte.
- **Coûts** à réévaluer après trois mois d'utilisation.

## Références

- `.env.example`, `config/app.php` (`timezone`), `config/fortify.php`, `config/session.php`,
  `config/filesystems.php`, `config/mail.php`, `config/deliveries.php`
- `app/Providers/FortifyServiceProvider.php`, `app/Providers/AppServiceProvider.php`
- `routes/console.php`, `.github/workflows/laravel-ci.yml` (racine du dépôt)
- `database/migrations/`, `database/schema/sqlite-schema.sql`, `database/seeders/ReferenceDataSeeder.php`
- `database/migrations/2026_10_01_234108_migrate_user_roles_to_permission_tables.php`
- [ADR 0002](0002-auth.md), [ADR 0006](0006-database.md), [ADR 0007](0007-reference-data-seeders.md),
  [ADR 0012](0012-inventory-by-store.md), [ADR 0013](0013-store-scope.md)
- Laravel Cloud : https://cloud.laravel.com/docs
- `.cursor/skills/deploying-to-cloud/SKILL.md`
