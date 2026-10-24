# ADR 0012 — Inventaire par magasin

- **Statut :** Accepté
- **Date :** 2026-10-24
- **Issue liée :** #100
- **Remplace en partie :** [ADR 0011](0011-inventory-movements-and-adjustments.md) (« le stock est un total pour
  l'entreprise »), [ADR 0009](0009-price-labels.md) (stock en démo)

## Contexte

Jusqu'ici, l'application gardait une seule quantité par produit et par état pour toute l'entreprise
(`inventory_stocks`). Les unités FIFO (`inventory_units`) et le journal (`inventory_movements`) n'avaient pas de
magasin non plus, et la prise d'inventaire annuelle (#88) comptait tout le stock en une fois.

En pratique, chaque magasin fait sa prise à une date différente, au moins une fois par année fiscale (du 1er février
au 31 janvier). Pour finaliser un seul magasin, il faut connaître son stock attendu. Sinon, la finalisation compterait
comme pertes le stock des autres magasins, avec les écritures qui suivent.

## Options considérées

### Option A — Garder un total pour l'entreprise et noter le magasin au comptage seulement

- ✅ Aucun changement au journal ni aux unités
- ❌ L'attendu d'un magasin reste inconnu : impossible de finaliser un magasin seul

### Option B — Stock, unités et journal par magasin ✔️

Chaque quantité, chaque unité et chaque mouvement appartient à un magasin. Les invariants de l'ADR 0011 deviennent des
invariants par magasin.

- ✅ L'attendu d'un magasin est exact, et sa prise ne touche que lui
- ✅ Le journal dit où chaque mouvement s'est produit
- ❌ Chaque document qui touche l'inventaire doit savoir de quel magasin il s'agit

## Décision

Nous retenons l'**option B**.

**Le stock :**

- `inventory_stocks` a une ligne par produit et par magasin (`UK product_id, store_id`). `inventory_units`,
  `inventory_movements` et `inventory_adjustments` ont leur magasin.
- `InventoryMovement::record($product, $store, …)` reçoit le magasin en deuxième paramètre. Les unités FIFO sorties,
  créées ou rouvertes sont celles de ce magasin.
- `Product::stockAt($storeId)` donne le stock d'un magasin. `Product::totalQuantity($status)` donne le total de
  l'entreprise : il sert au stock en démo des étiquettes, qui restent comptées pour toute l'entreprise (ADR 0009).
  La fiche produit a un onglet **Inventaire**, avec les quantités par magasin et le total.

**Les achats :**

- Une commande fournisseur a un **magasin de destination** (`supplier_orders.store_id`), par défaut celui de l'usager
  (`Store::defaultIdFor()` : son magasin, sinon le premier magasin physique, le bureau chef). Un fournisseur a au plus
  un brouillon **par magasin**.
- Une réception a le magasin qui reçoit, celui de la commande. Un usager rattaché à un magasin ne voit et ne reçoit
  que les commandes destinées à son magasin. Une réception ne couvre qu'un magasin.

**Les ventes :**

- Une commande client est servie par le stock de l'**entrepôt** de son magasin (`stores.warehouse_store_id`), sinon
  par le magasin lui-même (`CustomerOrder::stockStoreId()`). Réservation, ramassage, livraison, expédition, retour
  et défectueux s'y font.
- La réservation ne regarde que ce stock. Le reste est commandé au fournisseur, sur le brouillon de ce magasin. Le
  stock d'un autre magasin n'est pas utilisé.

**La prise d'inventaire :**

- Une prise a son magasin (`inventory_counts.store_id`), et un magasin a au plus une prise en cours. Deux magasins
  peuvent compter en même temps.
- Le gel (`InventoryCount::stockIsFrozen($storeId)`) ne bloque que les mouvements du magasin compté.
- Les lignes, l'attendu (`InventoryCountLine::expected()`), les perdus remis en stock et les feuilles de compte
  (zones du magasin, #96) sont ceux du magasin.
- La finalisation crée les ajustements de ce magasin. Leurs écritures portent le magasin (`ledger_entries.store_id`)
  et la date du comptage.
- **Année fiscale** : `config/inventory.php` (`fiscal_year_start`, `02-01` : du 1er février au 31 janvier). La page
  des prises montre, pour chaque magasin, sa dernière prise finalisée, et avertit s'il n'en a pas eu cette année
  (`InventoryCount::fiscalYearStatus()`).

**Le grand livre :**

- Les écritures d'inventaire (ajustements, prises) portent leur magasin. Les autres, comme les factures de
  transporteur, sont tenues par le bureau chef et n'ont pas de magasin.

**Les ajustements et les changements d'état manuels** se font dans le magasin choisi, par défaut celui de l'usager.

**Données existantes** : la migration attribue le stock, les unités, le journal, les ajustements, les prises, les
commandes fournisseurs et les réceptions existants au premier magasin physique. L'application n'est pas encore en
production : la base est rebâtie par `migrate:fresh --seed`.

## Conséquences

**Positives**

- Chaque magasin compte et finalise son inventaire quand il le veut, sans toucher aux autres.
- Le journal et les écritures d'inventaire se suivent par magasin.

**Négatives / limitations**

- Il n'y a pas encore de transfert entre magasins : la marchandise d'un magasin ne sert pas les ventes d'un autre.
- Si l'entrepôt d'un magasin change pendant qu'une commande a des articles réservés, ces articles restent dans le
  stock de l'ancien entrepôt.
- Un usager sans magasin (bureau chef) reçoit les commandes de tous les magasins.

**Protections mises en place**

- Les invariants I1 à I6 de l'ADR 0011 se vérifient magasin par magasin (`expectInventoryIntegrity()`, tests/Pest.php).
- `CustomerOrder::stockStoreId()` refuse de toucher à l'inventaire d'une commande sans magasin.
- Une réception refuse une commande d'un autre magasin que celui de l'usager, et ne mélange pas deux magasins.
- Une seule prise en cours par magasin (le magasin est verrouillé au démarrage).

## Questions ouvertes

- **Transferts entre magasins** (story à venir). Le stock du magasin qui expédie passerait « En transfert », puis
  « En transit » une fois le transfert accepté. Pour une commande client, ce serait « Client en transfert », puis
  « Client en transit » une fois confirmé. À la confirmation de la réception par le magasin qui reçoit, la ligne
  passerait « Transfert reçu » dans la commande client, et l'inventaire « En stock » ou « Réservé client ».
  - Chaque étape serait un mouvement apparié : les unités FIFO garderaient leur coût d'un magasin à l'autre.

## Références

- `database/migrations/2026_10_24_214309_add_store_to_inventory.php`
- `app/Models/InventoryMovement.php`, `app/Models/InventoryCount.php`, `app/Models/CustomerOrder.php` (`stockStoreId()`)
- `app/Models/Store.php` (`stockStoreId()`, `defaultIdFor()`), `config/inventory.php`
- `tests/Feature/InventoryByStoreTest.php`
