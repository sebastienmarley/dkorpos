# ADR 0007 — Rôles et permissions gérés par les seeders, synchronisés à chaque déploiement

- **Statut :** Accepté
- **Date :** 2026-10-10

## Contexte

Les rôles et permissions par défaut sont déclarés dans `config/access.php`
([ADR 0001](0001-permissions.md)). Jusqu'ici, ils arrivaient en base par deux chemins :

- **les migrations** : une trentaine de migrations de données (`add_invoice_permissions`,
  `add_customer_order_permissions`…) créaient chaque nouvelle permission et l'accordaient à
  **tous** les rôles existants (`Role::all()->each(...)`) ;
- **les seeders** (`PermissionSeeder`, `RoleSeeder`) : ils lisaient la config, mais les tests ne les
  exécutaient pas.

Cette situation posait trois problèmes :

1. **Le dump du schéma était inutilisable.** Un dump (`php artisan schema:dump`) ne contient que la
   structure. Avec un dump à jour, les migrations de données sont considérées comme déjà exécutées
   et les rôles n'existent plus : tous les tests qui créent un usager échouaient
   (`There is no role named salesman`). Le
   dump était donc resté figé à 7 migrations sur 129.
2. **Les migrations et la config ne donnaient pas les mêmes accès.** Avec les migrations, on obtient
   342 permissions accordées ; avec la config, 259. Un vendeur, par exemple, pouvait supprimer des
   factures et des commandes fournisseurs alors que `config/access.php` ne le prévoit pas. 62 tests
   reposaient sans le savoir sur ces accès élargis.
3. **Rien n'était prévu pour la production.** On ne peut pas y faire `migrate:fresh --seed`. Or
   `RoleSeeder` n'accordait des permissions qu'à un rôle qu'il venait de créer : une nouvelle
   permission n'aurait été accordée à aucun rôle existant, pas même à l'administrateur, puisqu'il n'y
   a pas de `Gate::before`.

## Options considérées

### Option A — Garder les données dans les migrations

Chaque nouvelle permission arrive par une migration, comme avant.

- ✅ Fonctionne déjà, et s'applique naturellement au déploiement
- ❌ Rend le dump inutilisable, donc impossible de regrouper les migrations
- ❌ La liste des rôles est écrite deux fois (config et migration), et les deux divergent déjà

### Option B — Seeders qui resynchronisent toute la matrice à chaque déploiement

`RoleSeeder` fait un `syncPermissions()` complet de chaque rôle selon la config.

- ✅ La base est toujours identique à la config
- ❌ Écrase à chaque déploiement ce qu'un gérant a modifié dans l'interface, alors que
  l'[ADR 0001](0001-permissions.md) prévoit justement des ajustements par l'interface

### Option C — Seeders qui ne font que créer ce qui manque

Le comportement d'avant : on crée les permissions et les rôles manquants, et seul un rôle nouveau
reçoit des permissions.

- ✅ Ne touche jamais aux ajustements faits dans l'interface
- ❌ Une nouvelle permission n'est accordée à aucun rôle existant : il faudrait l'accorder à la main
  dans chaque base

### Option D — Seeders qui accordent seulement les permissions qu'ils viennent de créer ✔️

À chaque déploiement, `PermissionSeeder` crée les permissions manquantes et renvoie leur liste.
`RoleSeeder` accorde ensuite ces nouvelles permissions aux rôles existants prévus par la config.

- ✅ `config/access.php` est la seule source : plus de migration de permissions à écrire
- ✅ Les ajustements faits dans l'interface sont conservés : une permission existante n'est jamais
  réaccordée ni retirée
- ✅ Le même code sert à l'installation, aux tests et au déploiement
- ❌ Ne couvre pas le renommage ni la suppression d'une permission

## Décision

Nous retenons l'**option D**.

**Données de référence**

- `ReferenceDataSeeder` regroupe ce dont l'application a besoin pour fonctionner : permissions et
  rôles (`RoleSeeder`, qui appelle `PermissionSeeder`) et le mode de paiement « Comptant ».
- `DatabaseSeeder` l'appelle, puis ajoute les données de démo.
- Les tests l'exécutent après les migrations (`$seed` et `$seeder` dans `tests/TestCase.php`).
- Les migrations ne contiennent plus que la structure. Les anciennes migrations de données restent
  en place, puisqu'une migration partagée ne se modifie pas ([ADR 0006](0006-database.md)). Le dump
  les marque comme déjà passées sur une installation neuve.

**Comportement de `RoleSeeder`**

| Situation | Effet |
|---|---|
| Rôle nouveau | Reçoit toutes ses permissions selon la config |
| Rôle existant, permission nouvelle qu'il liste | La reçoit |
| Rôle `'*'` (admin, propriétaire), permission nouvelle | La reçoit |
| Rôle existant, permission déjà existante | Aucun changement, même si elle a été retirée dans l'interface |

`RoleSeeder` exécute `PermissionSeeder` et l'attribution des permissions dans **une seule
transaction**. Sans elle, une exécution qui plante après la création des permissions laisserait
celles-ci en base sans rôle. Au passage suivant, elles ne seraient plus « nouvelles », donc jamais
accordées. Avec la transaction, un échec annule tout, et le passage suivant reprend à zéro.

**Matrice de référence**

En cas d'écart, c'est `config/access.php` qui fait foi, pas l'ancienne matrice des migrations. Les
tests qui dépendaient des accès élargis agissent maintenant avec un administrateur, comme les autres
tests de ces domaines.

**Déploiement**

```bash
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force
```

## Conséquences

**Positives**

- Ajouter une permission se fait à un seul endroit : `config/access.php`.
- Le dump du schéma est à jour (129 migrations), et on pourra regrouper les migrations avant la mise
  en production.
- Une installation neuve donne exactement les accès prévus par la config.
- Relancer le seeder sur une base à jour ne change rien.

**Négatives / limitations**

- **Bases existantes :** une base migrée avant cette décision garde la matrice élargie des anciennes
  migrations. Le seeder ne retire rien, donc il ne la corrige pas.
- **Renommer ou supprimer** une permission ou un rôle demande encore une migration ponctuelle. Une
  permission retirée de la config reste en base.
- **Permission dont le nom existe déjà :** une permission retirée de la config puis remise n'est pas
  « nouvelle ». Elle n'est donc accordée à aucun rôle existant.
- **Déploiement en deux commandes :** oublier la seconde laisse les nouvelles permissions sans rôle.

**Protections mises en place**

- `RoleSeederTest` simule un déploiement : la nouvelle permission va aux bons rôles, et une
  permission retirée dans l'interface n'est pas rétablie. Il vérifie aussi qu'après un passage
  qui échoue, le passage suivant accorde bien les nouvelles permissions.
- La transaction de `RoleSeeder` rend chaque passage tout ou rien.
- `PagePermissionsTest` et `ActionPermissionsTest` vérifient que les rôles par défaut correspondent
  à `config/access.php`.
- Une permission listée pour un rôle mais absente de `permissions` fait échouer le seeder sur une
  base neuve (`PermissionDoesNotExist`), donc dans les tests, au lieu d'être ignorée en silence.

## Questions ouvertes

- **Réaligner les bases existantes** sur `config/access.php` par une migration ponctuelle ?
- **Ajouter la seconde commande au script de déploiement** dès qu'il existera (ex. : commandes de
  déploiement de Laravel Cloud).

## Références

- `config/access.php`
- `database/seeders/ReferenceDataSeeder.php`, `RoleSeeder.php`, `PermissionSeeder.php`
- `tests/TestCase.php`, `tests/Feature/RoleSeederTest.php`
- [ADR 0001](0001-permissions.md) : contrôle d'accès hybride
- [ADR 0006](0006-database.md) : base de données et dump du schéma
