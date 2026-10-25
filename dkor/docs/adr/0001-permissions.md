# ADR 0001 — Contrôle d'accès hybride : rôles, permissions et magasin d'appartenance

- **Statut :** Accepté
- **Date :** 2026-10-05
- **Révisé le :** 2026-10-24 (ajout du magasin d'appartenance et de `stores.all`)

## Contexte

Il faut un système pour limiter les actions des utilisateurs de l'application.

La réalité du magasin est nuancée : les employés sont polyvalents. Un nouveau vendeur n'a besoin
que du strict minimum, alors qu'un employé à temps plein d'expérience peut s'occuper des changements
de prix, qu'un autre gère le catalogue et qu'un troisième aide avec les rendez-vous des clients.
Deux employés qui ont le même titre n'ont donc pas forcément les mêmes accès.

L'entreprise a aussi **plusieurs magasins** (ADR 0012). Un directeur gère son magasin : ses employés,
ses commandes clients, ses livraisons, son inventaire. Il ne doit ni voir ni modifier ceux d'un autre
magasin. Le bureau chef (propriétaire, comptabilité) travaille au contraire pour tous les magasins :
le propriétaire embauche et gère les employés de chacun de ses magasins.

Jusqu'ici, la règle « un usager sans magasin agit pour tous » était implicite et vérifiée à la pièce
(transferts, réceptions). Les autres pages laissaient choisir n'importe quel magasin. Comme un
employé est toujours créé avec un magasin, la règle ne servait en pratique qu'aux comptes créés à
la main.

## Options considérées

### Ce que l'usager peut faire

#### Option A — Rôles seulement

Chaque utilisateur se voit attribuer un rôle, et ce rôle donne des droits d'accès.

- ✅ Simple, rapide et pratique
- ❌ Trop rigide pour des employés polyvalents : il faudrait un rôle par combinaison de tâches

#### Option B — Permissions seulement

Les permissions sont définies dans un enum, et `@can('…')` vérifie si l'utilisateur authentifié peut
faire une action.

- ✅ Contrôle très fin : un bouton, une page, une route, voire une portion de code dans un `if`
- ❌ Gestion à la pièce : à l'embauche, il faut ajouter chaque permission à l'employé
- ❌ Demande plus d'attention ; des groupes de permissions réduiraient les clics, sans régler le fond

#### Option C — Hybride avec `spatie/laravel-permission` ✔️

Quelques rôles hiérarchiques, chacun avec des permissions par défaut. On ajoute ou retire ensuite
des permissions à la pièce dans la fiche de l'usager.

- ✅ La rapidité des rôles à l'embauche
- ✅ La souplesse des permissions pour les cas particuliers
- ❌ Les accès réels d'un usager dépendent de deux sources (son rôle et ses ajustements)

### Pour quel magasin il peut le faire

#### Option D — Un usager sans magasin agit pour tous

On garde la règle actuelle et on la centralise.

- ✅ Aucune nouvelle notion
- ❌ L'accès à tous les magasins dépend d'un champ vide, pas d'une décision visible dans les accès
- ❌ On ne peut pas donner au propriétaire un magasin d'appartenance (horaire, paie, magasin par
  défaut) sans lui retirer l'accès aux autres

#### Option E — Rôles par magasin (`teams` de `spatie/laravel-permission`)

Chaque usager a un rôle et des permissions différents dans chaque magasin.

- ✅ Très souple (ex. : vendeur dans un magasin, directeur dans un autre)
- ❌ Lourd : chaque vérification de permission dépend du magasin courant
- ❌ Ne correspond pas à la réalité : un employé travaille dans un seul magasin

#### Option F — Magasin d'appartenance et permission `stores.all` ✔️

Chaque usager appartient à un magasin (`users.store_id`). Une permission, `stores.all`, donne
accès à tous les magasins.

- ✅ Une seule notion à vérifier : « l'usager peut-il agir pour ce magasin ? »
- ✅ L'accès à tous les magasins s'accorde ou se retire comme toute autre permission
- ✅ Le propriétaire garde un magasin d'appartenance
- ❌ Pas d'accès à quelques magasins seulement (ex. : un directeur régional)

## Décision

Nous retenons l'**option C** pour les permissions et l'**option F** pour les magasins.

### Rôles et permissions

- Les permissions et les rôles par défaut sont définis dans `config/access.php`, et créés par
  `RoleSeeder` à chaque déploiement. Une permission ajoutée à un rôle par défaut n'est accordée aux
  rôles existants que si elle vient d'être créée : ce qui a été retiré dans l'interface ne revient pas.
- Un usager a **un seul rôle** (`User::role`). Le rôle a un **niveau** hiérarchique (`roles.level`) :
  on ne gère que les usagers dont le rôle est de niveau inférieur ou égal au sien
  (`User::assignableRoles()`).
- La fiche de l'usager ajuste son rôle : des **permissions ajoutées** (permissions directes de Spatie)
  et des **permissions retirées** (`user_denied_permissions`). Une permission retirée l'emporte sur
  le rôle et sur les ajouts (`User::hasPermissionTo()`).
- C'est la permission, jamais le rôle, qui est vérifiée dans le code : `middleware('can:…')` sur la
  route, puis `$this->authorize('…')` dans chaque méthode Livewire qui modifie des données (ADR 0005).

### Magasin d'appartenance

- Chaque usager a un **magasin d'appartenance** (`users.store_id`), exigé à la création et dans la
  fiche. C'est son magasin par défaut partout (`Store::defaultIdFor()`).
- **`stores.all` (« Tous les magasins »)** donne accès à tous les magasins. Elle est accordée par
  défaut à l'administrateur, au propriétaire et à la comptabilité.
- **Sans `stores.all`, un usager ne voit et n'agit que pour son magasin.** Ce qui n'a pas de magasin
  n'est accessible qu'avec `stores.all`.
- La règle est écrite à un seul endroit :
  - `User::hasAllStores()` et `User::canActForStore($storeId)` ;
  - le scope `accessibleTo($user)` : `Store::accessibleTo()` pour les magasins, et le trait
    `App\Concerns\BelongsToStore` pour les tables qui ont un `store_id` (`CustomerOrder`,
    `CustomerOrderDelivery`, `SupplierOrder`, `Reception`, `InventoryCount`, `InventoryAdjustment`,
    `InventoryMovement`), avec `isAccessibleTo($user)` pour un seul enregistrement ;
  - le trait Livewire `App\Concerns\ChoosesStore` pour les pages qui affichent un magasin
    (`$storeId`). Le sélecteur n'est montré qu'avec `stores.all`. Un magasin inaccessible (lien
    modifié, requête trafiquée) est ramené au magasin d'appartenance.

**Ce que la règle couvre :**

| Domaine | Sans `stores.all` | Avec `stores.all` |
|---|---|---|
| **Usagers** | Ne voit et ne gère que les employés de son magasin (`User::visibleTo()`, `UserPolicy`). Embauche dans son magasin seulement. Ne change pas le magasin d'appartenance (`UserPolicy::changeStore()`). | Tous les employés, filtre par magasin. Embauche dans n'importe quel magasin et change le magasin d'appartenance. |
| **Commandes clients** | Liste et fiche limitées à son magasin (403 sinon). Une nouvelle commande est créée dans son magasin. | Toutes les commandes, filtre par magasin. Choix du magasin à la création. |
| **Livraisons et expéditions** | Livraisons, Mes livraisons et Expéditions de son magasin. | Sélecteur de magasin. |
| **Inventaire** | Ajustements, déplacements d'état et prises d'inventaire dans son magasin (listes, formulaires, fiche d'une prise et feuilles de compte). Zones et emplacements de son magasin. | Tous les magasins. |
| **Réceptions** | Liste, fiche et réception des commandes destinées à son magasin. | Tous les magasins. |
| **Transferts** | Agit pour son magasin (`StoreTransferGuard::actingFor()`). Pour un client, le magasin de la commande demande, même si son entrepôt reçoit. | Agit pour chaque magasin, avec le sélecteur. |

Les données de consultation communes restent visibles pour tous : catalogue, stock des autres
magasins (onglet **Inventaire** d'un produit, sources d'un transfert), clients.

## Conséquences

**Positives**

- Un nouvel employé est fonctionnel dès qu'on lui attribue un rôle et un magasin.
- Les accès suivent la polyvalence réelle des employés.
- Un directeur gère son magasin sans voir les autres, et le bureau chef garde une vue d'ensemble
  grâce à une seule permission.
- Une nouvelle page qui touche un magasin réutilise `accessibleTo()`, `isAccessibleTo()` ou
  `ChoosesStore` au lieu de récrire la règle.

**Négatives / limitations**

- Pour savoir ce qu'un usager peut faire, il faut regarder son rôle, ses ajustements individuels et
  son magasin.
- Un usager n'a qu'un magasin : un directeur de deux magasins demande `stores.all`, qui ouvre tous
  les magasins.
- Un ancien employé d'un autre magasin qui revient n'est pas proposé à un directeur : le propriétaire
  doit le réactiver et changer son magasin.
- Les commandes fournisseurs, les horaires et la paie ne sont pas encore limités au magasin
  (voir Questions ouvertes).

**Protections mises en place**

- Une personne ne peut pas modifier sa propre fiche sans la permission requise (`users.edit_self`),
  réservée à l'admin et au propriétaire.
- On ne peut pas accorder ni retirer une permission qu'on n'a pas. Exemple : un directeur ne peut pas
  donner l'accès à la gestion des rôles à un vendeur, ni `stores.all`.
- Le module est caché à un utilisateur qui n'a pas la permission. S'il entre le lien dans la barre
  d'adresse, il reçoit une erreur 403.
- La fiche d'un usager d'un autre magasin répond 404 ; une commande client, une réception ou une
  prise d'inventaire d'un autre magasin répond 403.
- Les formulaires valident le magasin choisi contre les magasins accessibles, et non seulement contre
  les magasins existants.
- `PagePermissionsTest` et `ActionPermissionsTest` vérifient les permissions ; `StoreAccessTest` et
  les tests des usagers, des transferts et de l'inventaire par magasin vérifient le magasin.

## Questions ouvertes

- Limiter au magasin les commandes fournisseurs (aujourd'hui surtout utilisées par la comptabilité),
  la gestion des horaires et la paie.
- Accès à quelques magasins seulement (table pivot usager ↔ magasins) si un poste régional apparaît.

## Références

- `spatie/laravel-permission` ^8.3, `config/permission.php`, `config/access.php`
- `app/Models/User.php` (`hasPermissionTo()`, `assignableRoles()`, `visibleTo()`, `hasAllStores()`,
  `canActForStore()`), `app/Policies/UserPolicy.php`
- `app/Models/Store.php` (`accessibleTo()`, `defaultIdFor()`), `app/Concerns/BelongsToStore.php`,
  `app/Concerns/ChoosesStore.php`
- `database/migrations/2026_10_04_151917_create_user_denied_permissions_table.php`
- `tests/Feature/StoreAccessTest.php`, `tests/Feature/Users/`
