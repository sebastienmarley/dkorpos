# ADR 0012 — Inventaire par magasin

- **Statut :** Accepté
- **Date :** 2026-10-24
- **Issues liées :** #100 (stock par magasin), #103 (transferts)
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
- Une réception a le magasin qui reçoit, celui de la commande. Un usager sans `stores.all` ne voit et ne reçoit
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

**Les transferts entre magasins (#103) :**

- Le **magasin qui reçoit** demande toujours le transfert (`RequestStoreTransfer`). Le magasin qui expédie l'accepte
  ou le refuse avec une raison (`AcceptStoreTransfer`, `CloseStoreTransfer`). Le demandeur peut l'annuler tant qu'il
  n'est pas en transit. Le magasin qui reçoit confirme la quantité reçue (`ReceiveStoreTransfer`). Chacun n'agit que
  pour son magasin ; un usager qui a `stores.all` (bureau chef) agit pour tous ([ADR 0013](0013-store-scope.md)).
- **Pour le stock**, la marchandise part de « En stock » : « En transfert », puis « En transit », puis « En stock »
  chez le magasin qui reçoit. Un démonstrateur ne se transfère pas pour devenir démo ailleurs.
- **Pour un client**, elle part de « En stock », ou de « En démo » (la dernière unité) : « Client en transfert »,
  puis « Client en transit », puis « Réservé client » chez le magasin qui reçoit.
  - La quantité est séparée de la ligne en une ligne « En commande », sans ligne fournisseur, qui affiche « Transfert
    de [magasin] » et ne se modifie plus (`CustomerOrder::takeLineForTransfer()`).
  - À la réception, la ligne passe « Transfert reçu » (`CustomerOrderLineStatus::TransferReceived`), qui se ramasse,
    se livre et s'expédie comme « Reçu ».
  - Une ligne dont la commande fournisseur est déjà envoyée ne se transfère pas : il faut d'abord l'annuler chez le
    fournisseur.
  - Un transfert pour un client refusé ou annulé fait commander la ligne au fournisseur, et les vendeurs sont avisés
    pour prévenir le client du délai.
- **Les unités FIFO** restent au magasin qui expédie jusqu'à la réception. `InventoryMovement::moveBetweenStores()`
  fait alors passer les mêmes unités au magasin qui reçoit, à leur coût, avec une sortie (`TransferSent`) et une ou
  plusieurs entrées (`TransferReceived`) appariées et du même montant.
- **Un écart à la réception** devient « Perdu » chez le magasin qui reçoit. Pour un client, le manquant est commandé
  au fournisseur.
- Les défectueux et les pièces ne se transfèrent pas. Aucun transfert ne bouge pendant le gel du stock de l'un des
  deux magasins.
- **Les avis** (cloche, `StoreTransferNotice`) vont au magasin qui expédie (demande, annulation), au magasin qui reçoit
  (en route), au demandeur, et aux vendeurs pour un client.
- **Permissions :**
  - `transfers.request` : vendeurs et directeurs ;
  - `transfers.accept` et `transfers.receive` : commis d'entrepôt et directeurs.
- **Page :** Inventaire > Transferts (`inventory.transfers`). La commande client montre, dans le modal de la ligne, les
  magasins qui ont l'article.

**Données existantes** : la migration attribue le stock, les unités, le journal, les ajustements, les prises, les
commandes fournisseurs et les réceptions existants au premier magasin physique. L'application n'est pas encore en
production : la base est rebâtie par `migrate:fresh --seed`.

## Conséquences

**Positives**

- Chaque magasin compte et finalise son inventaire quand il le veut, sans toucher aux autres.
- Le journal et les écritures d'inventaire se suivent par magasin.

**Négatives / limitations**

- Un transfert couvre un seul produit : plusieurs articles demandent plusieurs transferts.
- Si l'entrepôt d'un magasin change pendant qu'une commande a des articles réservés, ces articles restent dans le
  stock de l'ancien entrepôt.
- Un usager qui a `stores.all` (bureau chef) reçoit les commandes de tous les magasins.

**Protections mises en place**

- Les invariants I1 à I6 de l'ADR 0011 se vérifient magasin par magasin (`expectInventoryIntegrity()`, tests/Pest.php).
- `CustomerOrder::stockStoreId()` refuse de toucher à l'inventaire d'une commande sans magasin.
- Une réception refuse une commande d'un autre magasin que celui de l'usager, et ne mélange pas deux magasins.
- Une seule prise en cours par magasin (le magasin est verrouillé au démarrage).

## Questions ouvertes

- Le transport entre magasins (camion, bordereau, coût) et le réapprovisionnement automatique restent à faire.

## Références

- `database/migrations/2026_10_24_214309_add_store_to_inventory.php`
- `app/Models/InventoryMovement.php`, `app/Models/InventoryCount.php`, `app/Models/CustomerOrder.php` (`stockStoreId()`)
- `app/Models/Store.php` (`stockStoreId()`, `defaultIdFor()`), `config/inventory.php`
- `tests/Feature/InventoryByStoreTest.php`
- `database/migrations/2026_10_24_234353_create_store_transfers_table.php`, `app/Models/StoreTransfer.php`
- `app/Actions/RequestStoreTransfer.php`, `AcceptStoreTransfer.php`, `CloseStoreTransfer.php`, `ReceiveStoreTransfer.php`
- `app/Livewire/Inventory/Transfers.php`, `app/Livewire/CustomerOrders/LineTransfer.php`, `tests/Feature/StoreTransfersTest.php`
