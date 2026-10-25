# ADR 0013 — Portée par magasin : mon magasin seulement ou tous les magasins

- **Statut :** Accepté
- **Date :** 2026-10-24
- **Complète :** [ADR 0001](0001-permissions.md) (permissions), [ADR 0012](0012-inventory-by-store.md) (inventaire par
  magasin)

## Contexte

L'entreprise a plusieurs magasins (ADR 0012). Un directeur gère son magasin : ses employés, ses commandes clients,
ses horaires, ses livraisons, son inventaire. Il ne doit ni voir ni modifier ceux d'un autre magasin. Le bureau chef
(propriétaire, comptabilité) travaille au contraire pour tous les magasins : le propriétaire embauche et gère les
employés de chacun de ses magasins.

Les permissions (ADR 0001) disent **ce que** l'usager peut faire, pas **pour quel magasin**. Jusqu'ici, la règle
« un usager sans magasin agit pour tous » était implicite et vérifiée à la pièce (transferts, réceptions). Les autres
pages laissaient choisir n'importe quel magasin. Comme un employé est toujours créé avec un magasin, la règle ne
servait en pratique qu'aux comptes créés à la main.

## Options considérées

### Option A — Un usager sans magasin agit pour tous

On garde la règle actuelle et on la centralise.

- ✅ Aucune nouvelle notion
- ❌ L'accès à tous les magasins dépend d'un champ vide, pas d'une décision visible dans les accès
- ❌ On ne peut pas donner au propriétaire un magasin d'appartenance (horaire, paie, magasin par défaut) sans lui
  retirer l'accès aux autres

### Option B — Rôles par magasin (`teams` de `spatie/laravel-permission`)

Chaque usager a un rôle et des permissions différents dans chaque magasin.

- ✅ Très souple (ex. : vendeur dans un magasin, directeur dans un autre)
- ❌ Lourd : chaque vérification de permission dépend du magasin courant
- ❌ Ne correspond pas à la réalité : un employé travaille dans un seul magasin

### Option C — Magasin d'appartenance et permission `stores.all` ✔️

Chaque usager appartient à un magasin (`users.store_id`). Une permission, `stores.all`, donne accès à tous les
magasins.

- ✅ Une seule question à poser : « l'usager peut-il agir pour ce magasin ? »
- ✅ L'accès à tous les magasins s'accorde ou se retire comme toute autre permission
- ✅ Le propriétaire garde un magasin d'appartenance
- ❌ Pas d'accès à quelques magasins seulement (ex. : un directeur régional)

## Décision

Nous retenons l'**option C**.

- Chaque usager a un **magasin d'appartenance** (`users.store_id`), exigé à la création et dans la fiche. C'est son
  magasin par défaut partout (`Store::defaultIdFor()`).
- **`stores.all` (« Tous les magasins »)** donne accès à tous les magasins. Elle est accordée par défaut à
  l'administrateur, au propriétaire et à la comptabilité.
- **Sans `stores.all`, un usager ne voit et n'agit que pour son magasin.** Ce qui n'a pas de magasin n'est accessible
  qu'avec `stores.all`.
- **Avec `stores.all`, les pages offrent un sélecteur ou un filtre de magasin.** Le magasin d'appartenance reste
  choisi par défaut.

### Où la règle est écrite

- `User::hasAllStores()` et `User::canActForStore($storeId)`.
- Le scope `accessibleTo($user)` : `Store::accessibleTo()` pour les magasins, et le trait `App\Concerns\BelongsToStore`
  pour les tables qui ont un `store_id` (`CustomerOrder`, `CustomerOrderDelivery`, `SupplierOrder`, `Reception`,
  `InventoryCount`, `InventoryAdjustment`, `InventoryMovement`), avec `isAccessibleTo($user)` pour un seul
  enregistrement.
- Le trait Livewire `App\Concerns\ChoosesStore` pour les pages qui affichent un magasin (`$storeId`). Le sélecteur
  n'est montré qu'avec `stores.all`. Un magasin inaccessible (lien modifié, requête trafiquée) est ramené au magasin
  d'appartenance.

Une nouvelle page qui touche un magasin réutilise ces outils au lieu de récrire la règle.

### Ce que la règle couvre

| Domaine | Mon magasin seulement | Tous les magasins (`stores.all`) |
|---|---|---|
| **Usagers** | Ne voit et ne gère que les employés de son magasin (`User::visibleTo()`, `UserPolicy`). Embauche dans son magasin seulement. Ne change pas le magasin d'appartenance (`UserPolicy::changeStore()`). | Tous les employés, filtre par magasin. Embauche dans n'importe quel magasin et change le magasin d'appartenance. |
| **Commandes clients** | Liste et fiche limitées à son magasin. Une nouvelle commande est créée dans son magasin. | Toutes les commandes, filtre par magasin. Choix du magasin à la création. |
| **Gestion des horaires** | Grille, remplissage, publication, réassignation et rendez-vous à couvrir des employés actifs de son magasin (`ScheduleEdit`). | Sélecteur de magasin : chaque magasin publie sa semaine. |
| **Rendez-vous** | Le sélecteur d'employés n'offre que les employés actifs de son magasin (`Schedules\Appointments`). | Sélecteur de magasin, puis d'employé de ce magasin. |
| **Livraisons et expéditions** | Livraisons, Mes livraisons et Expéditions de son magasin. | Sélecteur de magasin. |
| **Inventaire** | Ajustements, déplacements d'état et prises d'inventaire dans son magasin (listes, formulaires, fiche d'une prise et feuilles de compte). Zones et emplacements de son magasin. | Tous les magasins. |
| **Réceptions** | Liste, fiche et réception des commandes destinées à son magasin. | Tous les magasins. |
| **Transferts** | Agit pour son magasin (`StoreTransferGuard::actingFor()`). Pour un client, le magasin de la commande demande, même si son entrepôt reçoit. | Agit pour chaque magasin, avec le sélecteur. |

**Restent communs à tous les magasins :** le catalogue, les clients, le stock des autres magasins en consultation
(onglet **Inventaire** d'un produit, sources d'un transfert), les modèles d'horaire et les jours fériés.

## Conséquences

**Positives**

- Un directeur gère son magasin sans voir les autres, et le bureau chef garde une vue d'ensemble grâce à une seule
  permission.
- La règle est la même partout et se teste à un seul endroit.

**Négatives / limitations**

- Pour savoir ce qu'un usager peut faire, il faut regarder ses permissions (ADR 0001) et son magasin.
- Un usager n'a qu'un magasin : un directeur de deux magasins demande `stores.all`, qui ouvre tous les magasins.
- Un ancien employé d'un autre magasin qui revient n'est pas proposé à un directeur : le propriétaire doit le
  réactiver et changer son magasin.
- Les commandes fournisseurs, la paie et le badge « rendez-vous à couvrir » du menu ne sont pas encore limités au
  magasin.

**Protections mises en place**

- La fiche d'un usager d'un autre magasin répond 404 ; une commande client, une réception ou une prise d'inventaire
  d'un autre magasin répond 403, comme une action sur l'horaire d'un employé d'un autre magasin.
- Les formulaires valident le magasin choisi contre les magasins accessibles, et non seulement contre les magasins
  existants.
- `StoreAccessTest` et les tests des usagers, des transferts et de l'inventaire par magasin vérifient la règle. Par
  défaut, `UserFactory` met les employés dans le même magasin : un test de séparation crée ses magasins
  explicitement.

## Questions ouvertes

- Limiter au magasin les commandes fournisseurs (aujourd'hui surtout utilisées par la comptabilité), la paie et le
  badge « rendez-vous à couvrir ».
- Accès à quelques magasins seulement (table pivot usager ↔ magasins) si un poste régional apparaît.

## Références

- `config/access.php` (`stores.all`)
- `app/Models/User.php` (`hasAllStores()`, `canActForStore()`, `visibleTo()`), `app/Policies/UserPolicy.php`
- `app/Models/Store.php` (`accessibleTo()`, `defaultIdFor()`), `app/Concerns/BelongsToStore.php`,
  `app/Concerns/ChoosesStore.php`
- `app/Livewire/Schedules/ScheduleEdit.php`, `app/Livewire/Schedules/Appointments.php`, `app/Actions/StoreTransferGuard.php`
- `tests/Feature/StoreAccessTest.php`
