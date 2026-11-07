# ADR 0018 — Ventes de démonstrateurs et liquidations

- **Statut :** Accepté
- **Date :** 2026-11-07
- **Issue liée :** #143
- **Complète :** [ADR 0011](0011-inventory-movements-and-adjustments.md) (journal d'inventaire, FIFO),
  [ADR 0012](0012-inventory-by-store.md) (stock par magasin) et [ADR 0017](0017-promotions.md) (promotions)

## Contexte

Deux façons de vendre sous le prix de vente étaient exclues des promotions (#84, ADR 0017) :

- **Le démonstrateur** : l'article exposé en magasin, souvent ouvert ou marqué, vendu à prix réduit et parfois
  « tel quel ». Les autres articles du même produit restent au prix régulier.
- **La liquidation** : un produit qu'un magasin écoule à prix réduit jusqu'à épuisement de son stock, sans date de
  fin.

Aujourd'hui, le vendeur baisse le prix de la ligne à la main. Rien ne dit au client qu'il achète un article de
plancher, le prix réduit n'est écrit nulle part, et rien n'empêche de commander au fournisseur un article au prix
de liquidation.

L'application suit déjà le stock en démo : `inventory_stocks.quantity_in_demo`, par magasin
([ADR 0012](0012-inventory-by-store.md)). Un article y passe par la page des mouvements d'inventaire (En stock →
En démo), il est compté à la prise d'inventaire et ses étiquettes de prix sont suivies
([ADR 0009](0009-price-labels.md)). En revanche, aucune commande ne sait vendre un article en démo.

Décisions prises avec le propriétaire avant cet ADR :

1. Un démonstrateur sort de l'inventaire **en FIFO**, comme toute vente : aucune unité n'est suivie.
2. Une liquidation a un **prix fixe** en dollars, **propre à chaque magasin**.
3. Un démonstrateur « tel quel » **ne se retourne pas**, mais une défectuosité passe toujours par le dossier
   défectueux.

## Options considérées

### Démonstrateurs

#### Option A — Suivre l'unité exposée

Le démonstrateur est une unité d'inventaire précise (`inventory_units`) qui porte son prix ; sa vente sort cette
unité, à son coût.

- ✅ Le coût vendu est celui de l'article exposé
- ❌ Déroge au FIFO de l'[ADR 0011](0011-inventory-movements-and-adjustments.md) : toutes les sorties devraient
  pouvoir viser une unité
- ❌ Écarté par le propriétaire (décision 1)

#### Option B — Une fiche de prix sur le stock en démo ✔️

Une **fiche de démonstrateur** (`product_demos`) donne un prix, une note et la mention « tel quel » à un article
du stock en démo d'un magasin. La vente réserve un article de ce stock ; la sortie suit le FIFO.

- ✅ Bâtie sur le stock en démo qui existe déjà (mouvements, prise d'inventaire, étiquettes)
- ✅ Aucun changement au FIFO ni au grand livre
- ❌ La fiche n'est pas attachée à un article physique : deux démonstrateurs du même produit dans un magasin se
  distinguent par leur note seulement

### Liquidations

#### Option A — Une promotion sans date de fin

- ✅ Réutilise les promotions
- ❌ Une promotion s'applique au paiement, sur toute l'entreprise, et une seule par commande : la liquidation est
  un prix par magasin, dès l'ajout du produit

#### Option B — Un prix de liquidation par produit et par magasin ✔️

`product_clearances` : le prix de liquidation d'un produit dans un magasin, actif jusqu'à ce qu'on y mette fin.
L'ajout du produit à une commande de ce magasin prend ce prix pour le stock en main.

- ✅ Le prix est appliqué sans geste du vendeur
- ✅ Par magasin, comme le stock
- ❌ Une ligne de commande de plus quand le client prend plus que le stock en main

## Décision

Nous retenons l'**option B** dans les deux cas.

### Les démonstrateurs

- Table `product_demos` : `product_id`, `store_id` (magasin du stock), `price` (cents), `note`, `is_as_is`.
- **Une fiche par article en démo.** Un magasin ne peut pas avoir plus de fiches libres d'un produit que d'articles
  en démo (`quantity_in_demo`). L'article passe d'abord en démo par la page des mouvements d'inventaire, comme
  aujourd'hui ; la fiche lui donne ensuite son prix.
- **Fiche prise** : une ligne de commande la référence (`customer_order_lines.product_demo_id`) et n'est pas
  annulée. Une fiche prise n'est offerte à aucune autre commande. Une fiche libre se retire ; une fiche prise, non.
- **Vente** (`CustomerOrder::addDemo()`) : une ligne d'un article, au prix de la fiche, avec sa note, qui réserve un
  article du stock en démo (En démo → Réservé client). Le prix régulier est gardé (`regular_unit_price` : le prix de
  vente du produit). La ligne n'est jamais fusionnée avec une autre et sa quantité ne change pas : rien n'est
  commandé au fournisseur pour un démonstrateur.
- **Réservation libérée** (ligne retirée ou annulée) : l'article revient **en démo**, pas en stock, et la fiche
  redevient libre.
- **Remise au client** : l'article sort en FIFO, comme toute vente ; la fiche reste prise. Un démonstrateur
  retourné revient en stock régulier : pour le revendre comme démonstrateur, on le replace en démo et on crée une
  nouvelle fiche.
- **« Tel quel »** : `returnLine()` refuse le retour. `returnDefective()` reste permis.

### Les liquidations

- Table `product_clearances` : `product_id`, `store_id`, `price` (cents), `ended_at`. **Une seule liquidation
  active** (sans `ended_at`) par produit et par magasin. Une liquidation ne se supprime pas : on y met fin, et les
  lignes qui l'ont reçue la gardent.
- **Ajout du produit** (`addProduct()`, sans prix imposé) dans un magasin qui le liquide :
  - la partie prise **dans le stock en main** va sur une ligne de liquidation au prix de liquidation
    (`customer_order_lines.product_clearance_id`, prix régulier gardé). Un ajout suivant s'additionne à cette ligne
    tant qu'elle est en inventaire ;
  - la partie **à commander** va sur une ligne ordinaire, au prix de vente. Un article en liquidation n'est jamais
    commandé au fournisseur à son prix de liquidation.
- **Une ligne de liquidation** ne peut pas recevoir de quantité en commande ; son prix se modifie comme toute ligne.
- Le magasin est celui du stock de la commande (`CustomerOrder::stockStoreId()`).
- Une liquidation n'exige pas que le produit soit discontinué : c'est au directeur d'en juger.

### Ce qui vaut pour les deux

- **Aucune promotion** ne s'ajoute à une ligne de démonstrateur ou de liquidation
  (`Promotion::eligibleLinesOf()` les exclut, en plus de la règle « déjà escompté » de l'ADR 0017).
- **Changer le prix à la main** garde le lien avec la fiche ou la liquidation : l'article reste un démonstrateur ou
  un article liquidé.
- **Grand livre** : la vente passe nette, au prix réduit, comme pour les promotions (ADR 0017).
- **Permission** `clearances.manage` (« Gérer — démonstrateurs et liquidations »), accordée au rôle Directeur, pour
  les magasins où l'usager peut agir (`User::canActForStore()`, [ADR 0013](0013-store-scope.md)).

### L'interface

- **Fiche produit** : une section « Démonstrateurs et liquidations » par magasin, avec le stock en démo, les fiches
  (prix, note, tel quel, libre ou prise) et la liquidation active (prix, fin).
- **Ajout d'un produit à une commande** : un résultat de recherche affiche « Liquidation X $ » quand le magasin de
  la commande le liquide, et un bouton par fiche de démonstrateur libre de ce magasin.
- **Ligne de commande** : badge « Démonstrateur » ou « Liquidation », prix régulier barré.
- **Document client** : « Démonstrateur », la note, « Vendu tel quel — non retournable » ; « Liquidation » ; le
  prix régulier.
- **Étiquette de prix** : la fiche produit imprime l'étiquette de liquidation d'un magasin (`products.label` avec
  `?store=`), qui porte « Liquidation » et le prix de liquidation. La file d'étiquettes de l'[ADR 0009](0009-price-labels.md)
  ne change pas : elle suit le prix de vente (`displayed_price`), commun à l'entreprise, alors qu'une liquidation
  est propre à un magasin. L'étiquette d'un démonstrateur garde aussi le prix régulier.

## Conséquences

**Positives**

- Le vendeur n'a plus de prix à calculer, et le client sait ce qu'il achète.
- Le stock en démo, le FIFO et le grand livre restent tels quels.
- Un article liquidé ne peut plus être commandé au fournisseur à perte.

**Négatives / limitations**

- Une fiche de démonstrateur n'est pas rattachée à un article physique précis : la note fait la différence.
- Déclarer un démonstrateur se fait en deux gestes : passer l'article en démo, puis créer sa fiche.
- Le coût vendu d'un démonstrateur est celui du FIFO, pas celui de l'article exposé.
- Une liquidation reste active quand le stock est épuisé : elle n'a simplement plus d'effet jusqu'à ce qu'on y
  mette fin ou que du stock revienne.
- L'étiquette d'un démonstrateur n'affiche pas son prix réduit.
- L'étiquette de liquidation s'imprime à la main ; à la fin d'une liquidation, rien ne rappelle de remettre
  l'étiquette au prix régulier.

**Protections mises en place**

- Pas plus de fiches libres que d'articles en démo dans le magasin.
- Une fiche prise ne se vend pas deux fois et ne se retire pas.
- La réservation d'un démonstrateur libérée revient en démo.
- Une ligne de liquidation ne passe jamais en commande fournisseur.
- Un démonstrateur « tel quel » ne se retourne pas ; le dossier défectueux reste ouvert.
- Les actions vérifient la permission et le magasin.

## Questions ouvertes

- **Étiquette du démonstrateur** : imprimer le prix de la fiche sur l'étiquette de l'article exposé.
- **Fin automatique** d'une liquidation quand le stock du magasin est épuisé.

## Références

- [ADR 0009](0009-price-labels.md) — étiquettes de prix des démonstrateurs
- [ADR 0011](0011-inventory-movements-and-adjustments.md) — journal d'inventaire et FIFO
- [ADR 0012](0012-inventory-by-store.md) — stock par magasin
- [ADR 0013](0013-store-scope.md) — portée par magasin
- [ADR 0017](0017-promotions.md) — promotions temporaires
- `app/Models/ProductDemo.php`, `app/Models/ProductClearance.php`
- `CustomerOrder::addDemo()`, `addProduct()`, `returnLine()`
- `tests/Feature/DemosAndClearancesTest.php`
