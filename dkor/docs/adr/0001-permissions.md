# ADR 0001 — Contrôle d'accès hybride : rôles et permissions

- **Statut :** Accepté
- **Date :** 2026-10-05
- **Révisé le :** 2026-10-24 (niveaux de rôle et permissions retirées; portée par magasin dans
  l'[ADR 0013](0013-store-scope.md))

## Contexte

Il faut un système pour limiter les actions des utilisateurs de l'application.

La réalité du magasin est nuancée : les employés sont polyvalents. Un nouveau vendeur n'a besoin
que du strict minimum, alors qu'un employé à temps plein d'expérience peut s'occuper des changements
de prix, qu'un autre gère le catalogue et qu'un troisième aide avec les rendez-vous des clients.
Deux employés qui ont le même titre n'ont donc pas forcément les mêmes accès.

## Options considérées

### Option A — Rôles seulement

Chaque utilisateur se voit attribuer un rôle, et ce rôle donne des droits d'accès.

- ✅ Simple, rapide et pratique
- ❌ Trop rigide pour des employés polyvalents : il faudrait un rôle par combinaison de tâches

### Option B — Permissions seulement

Les permissions sont définies dans un enum, et `@can('…')` vérifie si l'utilisateur authentifié peut
faire une action.

- ✅ Contrôle très fin : un bouton, une page, une route, voire une portion de code dans un `if`
- ❌ Gestion à la pièce : à l'embauche, il faut ajouter chaque permission à l'employé
- ❌ Demande plus d'attention ; des groupes de permissions réduiraient les clics, sans régler le fond

### Option C — Hybride avec `spatie/laravel-permission` ✔️

Quelques rôles hiérarchiques, chacun avec des permissions par défaut. On ajoute ou retire ensuite
des permissions à la pièce dans la fiche de l'usager.

- ✅ La rapidité des rôles à l'embauche
- ✅ La souplesse des permissions pour les cas particuliers
- ❌ Les accès réels d'un usager dépendent de deux sources (son rôle et ses ajustements)

## Décision

Nous retenons l'**option C**, avec `spatie/laravel-permission`.

- Les permissions et les rôles par défaut sont définis dans `config/access.php`, et créés par
  `RoleSeeder` à chaque déploiement. Une permission ajoutée à un rôle par défaut n'est accordée aux
  rôles existants que si elle vient d'être créée : ce qui a été retiré dans l'interface ne revient pas.
- Un usager a **un seul rôle** (`User::role`). Le rôle a un **niveau** hiérarchique (`roles.level`) :
  on ne gère que les usagers dont le rôle est de niveau inférieur ou égal au sien
  (`User::assignableRoles()`).
- Le rôle donne la base, et la fiche de l'usager l'ajuste : des **permissions ajoutées** (permissions
  directes de Spatie) et des **permissions retirées** (`user_denied_permissions`). Une permission
  retirée l'emporte sur le rôle et sur les ajouts (`User::hasPermissionTo()`).
- C'est la permission, jamais le rôle, qui est vérifiée dans le code : `middleware('can:…')` sur la
  route, puis `$this->authorize('…')` dans chaque méthode Livewire qui modifie des données (ADR 0005).
- La description de chaque permission est affichée en infobulle dans la fiche de l'usager, les rôles
  et la liste des permissions.

Les permissions disent ce que l'usager peut faire. Pour quel magasin il peut le faire relève de son
magasin d'appartenance et de la permission `stores.all` : voir l'[ADR 0013](0013-store-scope.md).

## Conséquences

**Positives**

- Un nouvel employé est fonctionnel dès qu'on lui attribue un rôle.
- Les accès suivent la polyvalence réelle des employés.

**Négatives / limitations**

- Pour savoir ce qu'un usager peut faire, il faut regarder son rôle et ses ajustements individuels.

**Protections mises en place**

- Une personne ne peut pas modifier sa propre fiche sans la permission requise (`users.edit_self`),
  réservée à l'admin et au propriétaire.
- On ne peut pas accorder ni retirer une permission qu'on n'a pas. Exemple : un directeur ne peut pas
  donner l'accès à la gestion des rôles à un vendeur.
- Le module est caché à un utilisateur qui n'a pas la permission. S'il entre le lien dans la barre
  d'adresse, il reçoit une erreur 403.
- `PagePermissionsTest` vérifie l'accès aux pages et `ActionPermissionsTest` l'accès aux actions.

## Références

- `spatie/laravel-permission` ^8.3, `config/permission.php`, `config/access.php`
- `app/Models/User.php` (`hasPermissionTo()`, `getAllPermissions()`, `assignableRoles()`),
  `app/Policies/UserPolicy.php`
- `database/migrations/2026_10_04_151917_create_user_denied_permissions_table.php`
