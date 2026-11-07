# ADR 0017 — Promotions temporaires

- **Statut :** Accepté
- **Date :** 2026-11-07
- **Issue liée :** #84
- **Complète :** [ADR 0008](0008-money-in-cents.md) (montants en cents) et
  [ADR 0016](0016-ledger-postings.md) (écritures des ventes)

## Contexte

Le magasin lance régulièrement des promotions de durée limitée : « nous payons les taxes », 10 % sur l'éclairage,
« épargnez 100 $ à l'achat de 2 électroménagers ». Aujourd'hui, le vendeur baisse les prix des lignes à la main,
sans trace de la promotion ni du prix régulier, et le calcul se fait à la calculatrice.

La demande (#84) :

- À l'étape du paiement, un bouton « Promotion » est actif quand un produit de la commande est en promotion.
- Le bouton applique la promotion aux produits visés. Les produits déjà escomptés ne sont pas touchés.
- Si plusieurs promotions s'appliquent, le bouton demande laquelle.
- Hors portée : ventes de démonstrateurs et liquidations.

Il faut donc décider de :

1. **La forme du rabais.** Une ligne de rabais séparée, ou des prix de ligne plus bas ?
2. **Le cumul.** Plusieurs promotions sur une commande, ou sur une même ligne ?
3. **« Déjà escompté ».** Comment le reconnaître sans nouveau champ sur la ligne ?
4. **Les produits visés.** Comment un gestionnaire désigne « l'éclairage » ou « les électroménagers » ?

## Options considérées

### Option A — Ligne de rabais négative sur la commande

La promotion ajoute une ligne « Promotion X » au montant négatif.

- ✅ Les prix des lignes ne changent pas
- ❌ Les taxes, le dépôt de 30 %, le ramassage partiel, les retours et les écritures de vente
  ([ADR 0016](0016-ledger-postings.md)) raisonnent tous par ligne : il faudrait répartir le rabais partout
- ❌ Un retour d'un seul article escompté ne sait pas quelle part du rabais reprendre

### Option B — Baisser le prix unitaire des lignes visées ✔️

La promotion remplace le prix unitaire des lignes admissibles et garde leur prix régulier à côté.

- ✅ Les taxes, le dépôt, le ramassage, les retours et le grand livre fonctionnent sans changement : ils lisent le
  prix de la ligne
- ✅ Le rabais d'un article retourné est repris tout seul (il est dans son prix)
- ✅ Le document client montre le prix régulier et le prix en promotion
- ❌ Un montant fixe doit être réparti en prix unitaires, avec des cents d'arrondi

### Option C — Prix promotionnel calculé à l'ajout du produit

Le prix de vente d'un produit tiendrait compte des promotions en vigueur.

- ✅ Aucun bouton à cliquer
- ❌ Contraire à la demande : le vendeur doit choisir d'appliquer la promotion à l'étape du paiement
- ❌ Ne permet pas « 100 $ à l'achat de 2 », qui dépend du contenu de la commande

## Décision

Nous retenons l'**option B**.

### Le modèle

- `promotions` : `name`, `type` (`PromotionType`), `percent` (décimal), `amount` (cents, `MoneyCast`),
  `minimum_quantity`, `starts_on`, `ends_on` (dates incluses), `is_active`.
- `promotion_targets` : ce que vise la promotion, par référence polymorphe (`target_type`, `target_id`) vers un
  **département, une catégorie, un fournisseur ou un produit**. Un produit est visé s'il correspond à l'une des
  cibles (son département passe par sa catégorie). Les alias `promotion` et `promotion_target` sont ajoutés au
  morph map ([ADR 0014](0014-production.md)).
- `customer_orders.promotion_id` : la promotion appliquée à la commande.
- `customer_order_lines.promotion_id` et `regular_unit_price` : la promotion de la ligne et son prix avant la
  promotion.

Les clés étrangères vers `promotions` sont en `restrict` : **une promotion ne se supprime pas**, elle se désactive.
Les commandes qui l'ont reçue la gardent.

### Les trois sortes de rabais

| Type | Paramètres | Nouveau prix unitaire |
|---|---|---|
| `Percent` | `percent` | prix − prix × pourcentage |
| `FixedAmount` | `amount`, `minimum_quantity` | rabais réparti sur les lignes visées au prorata de leur total |
| `TaxesPaid` | aucun | prix dont le total taxes incluses revient au prix d'origine : p² ÷ (p + taxes de p) |

- **Pourcentage** : appliqué au prix unitaire, arrondi au cent par `Money::percent()`.
- **Montant fixe** : offert seulement si la commande compte au moins `minimum_quantity` articles visés. Le rabais
  ne dépasse jamais le total des lignes. Il est réparti par `Money::allocate()` au prorata du total de chaque ligne,
  puis la part d'une ligne de plusieurs articles est arrondie à un multiple de sa quantité, car la ligne n'a qu'un
  prix unitaire. Les cents qui restent vont à une ligne d'un seul article, s'il y en a une.
- **Nous payons les taxes** : le magasin ne paie pas les taxes à la place du client. Il baisse le prix pour que le
  total, taxes incluses, égale le prix affiché (114,98 $ → 100 $, soit un prix de 86,97 $). Les taxes perçues et
  remises restent exactes. Seules les lignes **taxables** sont visées, avec les taux du magasin de la commande
  (`CustomerOrder::taxesFor()`).

### Les règles d'application

- **Une seule promotion par commande.** Une fois une promotion appliquée, `applicablePromotions()` est vide et
  `applyPromotion()` refuse toute autre promotion. Cumuler sur une même ligne poserait la question de l'ordre
  (10 % puis taxes payées ≠ l'inverse), et le magasin ne cumule pas ses promotions.
- **« Déjà escompté » = prix de la ligne sous le prix de vente du produit** (`unit_price < product.selling_price`).
  Une baisse manuelle, un prix de liste ou un remplacement à prix imposé exclut donc la ligne, sans nouveau champ.
- **Lignes admissibles** : un produit visé, pas sur mesure, pas déjà dans une promotion, et pas encore remis au
  client (`status->isPickable()` : en stock, en commande ou reçu). Une promotion peut donc s'appliquer au ramassage
  d'un article commandé il y a des semaines, si elle est en vigueur ce jour-là.
- **En vigueur** : promotion active, et la date du jour entre `starts_on` et `ends_on` inclusivement. La
  vérification est refaite dans la transaction d'application, avec la commande verrouillée.
- **Retirer la promotion** rétablit les prix réguliers. C'est refusé dès qu'un article escompté a été remis, retourné
  ou annulé : son prix est alors consigné dans un ramassage ou au grand livre.
- **Prix changé à la main** : la ligne quitte la promotion (`promotion_id` et `regular_unit_price` à null). Le
  vendeur a choisi un autre prix.
- **Ajout du même produit** : `addProduct()` ne fusionne jamais un ajout dans une ligne en promotion. Il crée une
  nouvelle ligne au prix de vente, que la promotion n'escompte pas (une seule application par commande).

### L'interface

- Page **Catalogue › Promotions** (`Catalog\Promotions`) : liste avec l'état (en cours, à venir, terminée,
  inactive), création et modification. Un produit se vise par son modèle, comparé sans espaces ni ponctuation
  (`UniqueCleanProductModel::clean()`). Un modèle commun à plusieurs fournisseurs est refusé : il faut viser le
  fournisseur.
- Permissions `promotions.view`, `promotions.create`, `promotions.edit`, accordées au rôle Directeur.
- Étape du paiement du ramassage (`CustomerOrders\Show`) : bouton « Promotion », grisé sans promotion admissible.
  Une seule promotion s'applique directement, plusieurs affichent un choix. Le montant à encaisser et le paiement
  prérempli sont recalculés. Une promotion appliquée s'affiche avec un bouton « Retirer ». L'action demande
  `customer_orders.edit`.
- Le prix régulier apparaît barré sur la ligne, et le document client (impression et courriel) indique
  « Promotion « nom » — prix régulier X ».

## Conséquences

**Positives**

- Le calcul ne se fait plus à la main, et il est le même pour tous les vendeurs.
- Taxes, dépôt, ramassage, retours et écritures de vente ([ADR 0016](0016-ledger-postings.md)) ne changent pas :
  la vente passe au prix escompté.
- Le prix régulier et la promotion de chaque ligne restent connus, pour le client et pour un rapport futur.

**Négatives / limitations**

- **Montant fixe** : si aucune ligne d'un seul article ne peut prendre les cents d'arrondi, le client épargne
  quelques cents de moins que le montant annoncé.
- **Taxes payées** : l'arrondi du prix avant taxes peut donner un total à un cent près du prix affiché.
- Le rabais n'est pas un compte distinct au grand livre : les ventes sont passées nettes de promotion (choix
  assumé, voir « Questions tranchées »). Le montant des rabais reste calculable par `regular_unit_price − unit_price`
  sur les lignes.
- Le bouton n'existe qu'à l'étape du paiement du ramassage (choix assumé). Une commande livrée ou expédiée sans
  passer par cette étape ne reçoit pas de promotion.
- Modifier une promotion ne touche pas les commandes qui l'ont déjà reçue.

**Protections mises en place**

- Application et retrait dans une transaction, la commande verrouillée, avec revalidation de la période et des
  conditions (`DomainException` sinon).
- Une seule promotion par commande, jamais deux fois sur une même ligne.
- Un produit déjà escompté, sur mesure ou déjà remis n'est jamais touché.
- Le rabais d'un montant fixe ne dépasse jamais le total des lignes visées.
- Les promotions ne se suppriment pas (`restrict`).

## Questions tranchées

- **Rapport des promotions** : pas nécessaire. Aucun rapport des rabais accordés n'est prévu.
- **Grand livre** : les ventes passent **nettes** de promotion, au prix escompté. Aucun compte de rabais distinct ;
  l'[ADR 0016](0016-ledger-postings.md) ne change pas.
- **Où appliquer une promotion** : **au ramassage seulement**, à l'étape du paiement, comme le demande l'issue.
  Aucun bouton sur la page de la commande, à la livraison ou à l'expédition.

## Questions ouvertes

- **Démonstrateurs et liquidations** (hors portée de #84) : un besoin différent (prix par unité ou par produit,
  pas par période), traité dans l'issue #143 et son propre ADR. Décisions déjà prises : un démonstrateur est un prix
  pour un article, qui sort de l'inventaire en FIFO comme toute vente ; une liquidation a un prix fixe par magasin ;
  un démonstrateur « tel quel » ne se retourne pas, mais une défectuosité passe toujours par le dossier défectueux. D'ici là, le vendeur baisse le prix de la ligne
  à la main : le produit devient « déjà escompté » et aucune promotion ne s'y ajoute.

## Références

- [ADR 0008](0008-money-in-cents.md) — montants en cents (`Money::percent()`, `Money::allocate()`)
- [ADR 0014](0014-production.md) — morph map
- [ADR 0016](0016-ledger-postings.md) — écritures des ventes
- `app/Models/Promotion.php`, `app/Models/PromotionTarget.php`, `app/Enums/PromotionType.php`
- `CustomerOrder::applicablePromotions()`, `applyPromotion()`, `removePromotion()`
- `app/Livewire/Catalog/Promotions.php`, `app/Livewire/CustomerOrders/Show.php`
- Migrations `2026_11_07_151420` à `2026_11_07_151422`
- `tests/Feature/PromotionsTest.php`
