# ADR 0004 — Figer le frais d'annulation sur la ligne de commande

- **Statut :** Accepté
- **Date :** 2026-10-09
- **Issue liée :** #59 (annulation d'une ligne)

## Contexte

Quand un client annule un article déjà commandé au fournisseur, le magasin peut facturer des frais
d'annulation. Ce frais dépend du magasin : chaque succursale a son propre pourcentage du prix
vendant, configurable dans sa fiche.

Deux contraintes s'opposent :

1. Le pourcentage d'un magasin **peut changer dans le temps**.
2. Un frais déjà facturé au client **ne doit jamais changer** après coup (comptabilité, reçus, litiges).

De plus, une commande client n'était jusqu'ici rattachée à aucun magasin, ce qui rendait impossible
de savoir quel pourcentage appliquer.

## Options considérées

### Option A — Recalculer le frais à l'affichage

Stocker seulement `stores.cancellation_fee_percent` et calculer `prix × pourcentage` à chaque lecture.

- ✅ Aucune donnée dupliquée
- ❌ Modifier le pourcentage d'un magasin change rétroactivement tous les frais déjà facturés
- ❌ Impossible de reproduire un ancien reçu

### Option B — Stocker le pourcentage sur la ligne

Copier le pourcentage dans `customer_order_lines` au moment de l'annulation.

- ✅ Historique préservé
- ❌ Le montant doit encore être recalculé (risque d'arrondi différent si la logique de calcul évolue)

### Option C — Stocker le montant calculé sur la ligne ✔️

Au moment de l'annulation, calculer le frais une seule fois et l'enregistrer dans
`customer_order_lines.cancellation_fee`.

- ✅ Le montant facturé est figé : ce qui est en base est ce que le client a payé
- ✅ Lecture simple, aucun calcul à l'affichage
- ❌ On perd le pourcentage exact appliqué (déductible au besoin)

## Décision

Nous retenons l'**option C**.

- `stores.cancellation_fee_percent` (decimal 5,2, défaut 0) : politique courante du magasin,
  validée entre 0 et 100.
- `customer_orders.store_id` (nullable, `nullOnDelete`) : rattache la commande au magasin dont on
  applique la politique.
- `customer_order_lines.cancellation_fee` (decimal 10,2, nullable) : montant figé au moment de
  l'annulation. `null` signifie qu'aucun frais n'a été facturé.

Le calcul est centralisé dans `CustomerOrder::cancellationFeeFor()` :
`quantité en commande × prix unitaire × pourcentage / 100`, arrondi à 2 décimales.
`CustomerOrder::cancelLineWithFee()` fige le montant sur la ligne « Annulé » dans une transaction.

## Conséquences

**Positives**

- Les reçus et rapports restent exacts même si un gérant change son pourcentage.
- Même principe que `unit_price`, déjà figé sur la ligne plutôt que lu depuis le produit.
- Le frais s'ajoute au sous-total de la commande (`recalculateBalance()`), donc il est taxé et
  inclus dans le solde.

**Négatives / limitations**

- Les commandes sans magasin (`store_id = null`, dont les commandes antérieures) se voient appliquer
  0 % de frais.
- Si un magasin est supprimé, `store_id` devient `null`, mais les frais déjà figés restent intacts.

**Protections mises en place**

- Seule une ligne « Commandé » ou « Demande d'annulation » avec une quantité en commande peut être
  annulée avec frais.
- Le frais s'applique seulement à la quantité en commande. La partie déjà en stock ou reçue reste
  au client.
- Le montant est affiché dans la confirmation avant l'annulation.

## Références

- Migrations `2026_10_09_150121`, `2026_10_09_150122` et `2026_10_09_150123`
- `app/Models/CustomerOrder.php` : `cancellationFeeFor()`, `cancelLineWithFee()`
