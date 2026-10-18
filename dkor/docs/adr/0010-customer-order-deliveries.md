# ADR 0010 — Livraisons des commandes clients : une notion commune, planifiée par demi-journée

- **Statut :** Accepté
- **Date :** 2026-10-18
- **Issue liée :** #12 (lien avec #86)

## Contexte

Aujourd'hui, une livraison se prépare sur papier. On imprime la commande, on encercle les produits à livrer et on
note la date voulue. Le livreur s'organise ensuite avec un agenda Google, s'il est à jour.

Deux façons de remettre une commande sans que le client se déplace sont à prévoir :

- **la livraison** (#12), avec nos ressources internes : nos livreurs, nos camions, notre horaire ;
- **l'expédition** (#86), par un fournisseur d'expédition, pour les petits colis.

Elles partagent plusieurs besoins : vérifier l'adresse avec le client, choisir les articles à remettre et
exiger le paiement. Seule la planification diffère : une demi-journée de nos camions, ou un envoi chez le
transporteur.

Plusieurs contraintes encadrent la solution.

1. **La remise au client existe déjà** pour le ramassage (`pickUp()` → `handOver()`). Elle sépare la ligne,
   sort l'inventaire en premier entré, premier sorti, et exige 100 % des articles remis plus le dépôt sur le
   reste (`amountRequiredFor()`).
2. **Une livraison se planifie souvent avant la réception** des articles commandés chez le fournisseur.
3. **La capacité dépend du magasin** : ses camions, et ses livreurs à l'horaire publié.

## Options considérées

### Option A — Une colonne « livraison » sur la commande ou sur ses lignes

- ✅ Simple
- ❌ Une seule livraison par commande, sans historique des reports ni des annulations
- ❌ Ne sert pas à l'expédition (#86)

### Option B — Des tables distinctes pour la livraison et l'expédition

- ✅ Chaque méthode a exactement ses colonnes
- ❌ L'adresse, les articles, le paiement et les statuts sont en double
- ❌ Les règles de quantités (ce qui reste à remettre, ce qui se ramasse) doivent consulter deux tables

### Option C — Une livraison commune, avec une méthode ✔️

`customer_order_deliveries` porte la méthode (`DeliveryMethod` : `Internal` ou `Shipping`), le statut,
l'adresse confirmée (copiée), le paiement à la livraison et l'historique (report, annulation).
`customer_order_delivery_lines` liste les quantités prévues par ligne de commande.

- ✅ Les règles communes existent à un seul endroit
- ✅ La #86 ajoute ses colonnes (transporteur, suivi) sans toucher aux règles de la #12
- ❌ Certaines colonnes ne servent qu'à une méthode (`scheduled_date` et `period` pour la livraison)

## Décision

Nous retenons l'**option C**.

**Commun aux deux méthodes (réutilisable par la #86) :**

- `CustomerOrderDelivery` et `CustomerOrderDeliveryLine`, avec les enums `DeliveryMethod` et `DeliveryStatus`
  (planifiée, en route, livrée, annulée). Une livraison **active** est planifiée ou en route.
- **L'adresse est copiée** de la fiche du client au moment de la vérification (`address_*`,
  `address_verified_by`, `address_verified_at`). Corriger la fiche ensuite ne change pas une livraison prévue.
  La vérification n'est pas gardée sur le client.
- **Les quantités** sont dans `CustomerOrder` :
  - `deliverableQuantities()` : produits et pièces pas encore remis, reçus ou non, moins ce qui est déjà prévu ;
  - `pickableQuantities()` : le ramassage en magasin exclut les articles prévus dans une livraison ;
  - `deliveringQuantities()` : ce que les livraisons actives réservent déjà.
- **Le paiement suit la règle du ramassage** : `amountRequiredForDelivery()` applique `amountRequiredFor()` aux
  articles de toutes les livraisons actives. S'il reste un montant à payer, on demande de régler la facture.
  Seule la permission `customer_orders.deliver_unpaid` (Directeur) peut choisir le paiement à la livraison
  (`pay_on_delivery`, `pay_on_delivery_authorized_by`).
- **La ligne n'est séparée qu'à sa remise au client**, comme pour un ramassage. Planifier une livraison ne change
  ni le statut des lignes, ni celui de la commande, ni l'inventaire.
- **Une livraison active ne prévoit jamais plus que ce qui reste à remettre au client.** Chaque changement d'une
  ligne après la planification appelle `CustomerOrder::syncDeliveries()`, dans sa transaction : annulation
  (`cancelLineWithFee()`, `applySupplierCancellation()`), retrait (`removeLine()`), réduction (`adjustLine()`,
  donc `updateLine()` et `updatePartLine()`, `updateCustomLine()`, `applySupplierQuantity()`) et remplacement
  (`applySupplierSubstitution()`, `reorderFromSupplier()`) :
  - la quantité prévue d'une ligne est ramenée à sa quantité, ou à 0 si la ligne est annulée, retirée ou n'est plus
    à remettre. On réduit d'abord la livraison la plus tardive : les planifiées avant celles en route ;
  - une **substitution** retire la ligne de la livraison, même si elle garde son identifiant : c'est un autre
    produit, à planifier de nouveau ;
  - un article **endommagé ou manquant** à la réception est recommandé sur une nouvelle ligne, du même produit. La
    quantité prévue y est reportée, dans la même livraison ;
  - une livraison qui n'a plus d'articles est annulée (« Plus aucun article à livrer ») et sa place se libère ;
  - les livreurs sont avisés après la transaction : « Livraison modifiée » avec le détail, ou « Livraison
    annulée ». Le paiement à la livraison déjà autorisé ne change pas.

**Propre à la livraison (#12) :**

- Les camions sont gérés dans l'onglet **Livraison** de la fiche du magasin (`delivery_trucks`, avec
  `capacity_per_period` : livraisons par demi-journée).
- La planification se fait **par demi-journée** (`DeliveryPeriod` : AM 8 h – 12 h, PM 12 h – 19 h), selon le
  nombre de camions et de livreurs disponibles au magasin, sans assigner de livreur. `DeliveryBooking` calcule la
  capacité :
  - un livreur (rôle `delivery`, rattaché au magasin) est disponible si son quart publié chevauche la
    demi-journée, sans absence ni férié fermé. Sans quart publié, il l'est après la semaine en cours, jusqu'à
    l'horizon des rendez-vous, comme dans `AppointmentBooking` ;
  - chaque camion actif part avec un livreur disponible, les plus gros d'abord. La capacité est la somme de
    leurs `capacity_per_period`.
- L'Action `ScheduleDelivery` planifie. **Un report est une annulation suivie d'une planification**, dans la même
  transaction (`rescheduled_from_id`). L'adresse vérifiée et le paiement à la livraison autorisé sont repris.
  L'Action `CancelDelivery` annule.
- La page **Livraisons** (`deliveries.view`) montre la grille de la semaine, par demi-journée : les places et
  les livraisons planifiées.
- **Notifications** (cloche, `DeliveryNotice`) aux livreurs du magasin quand une livraison est planifiée,
  reportée ou annulée. Ils sont aussi avisés quand un article à livrer est reçu avant la date planifiée
  (`NotifyDeliveryItemsReceived`, appelée par `applySupplierReceipt()`).

## Conséquences

**Positives**

- La #86 réutilise la vérification d'adresse, les articles, la règle de paiement et les statuts. Elle n'ajoute
  que sa méthode et ses colonnes d'expédition.
- Une livraison peut être planifiée avant la réception des articles, ce que le papier permettait déjà.

**Négatives / limitations**

- La remise au client à la livraison (passage à « Livré », sortie d'inventaire) reste à faire avec le menu du
  livreur, hors périmètre de la #12.
- La capacité compte des livraisons, sans tenir compte de leur durée, de leur distance ni du volume des articles.

**Protections mises en place**

- Une quantité ne peut pas être prévue deux fois, ni être ramassée en magasin pendant qu'une livraison la prévoit.
- Une livraison ne garde pas une ligne annulée, retirée ou réduite : elle suit la commande, ou elle est annulée.
- Une demi-journée complète ou commencée est refusée. La ligne du magasin est verrouillée pendant la planification.
- Seule une livraison planifiée (pas en route) peut être annulée ou reportée. Si la nouvelle demi-journée est
  refusée, le report est annulé en entier et la livraison d'origine reste planifiée.
- `customer_orders.edit` pour planifier, reporter ou annuler, `customer_orders.deliver_unpaid` pour le paiement à
  la livraison, `stores.edit` pour les camions.

## Questions ouvertes

- Remettre les articles au client depuis le menu du livreur : une méthode commune avec `pickUp()`, qui
  séparerait les lignes et encaisserait le paiement à la livraison.

## Références

- `database/migrations/2026_10_18_125336_create_delivery_trucks_table.php`
- `database/migrations/2026_10_18_125337_create_customer_order_deliveries_table.php`
- `app/Actions/ScheduleDelivery.php`, `app/Actions/CancelDelivery.php`, `app/Support/DeliveryBooking.php`
- `app/Livewire/CustomerOrders/Deliveries.php`, `app/Livewire/Deliveries/Index.php`
