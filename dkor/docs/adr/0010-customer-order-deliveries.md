# ADR 0010 — Livraisons des commandes clients : une notion commune, par camion ou par expédition

- **Statut :** Accepté
- **Date :** 2026-10-18
- **Issues liées :** #12 (livraison), #86 (expédition), #94 (menu du livreur)

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

**Commun aux deux méthodes :**

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
- **La validation et l'adresse sont partagées** : `CustomerOrder::guardDeliverableQuantities()` et
  `verifiedDeliveryAddress()` servent aux deux Actions (`ScheduleDelivery`, `RequestShipment`). Le composant
  `CustomerOrders/Deliveries` mène les deux parcours (adresse → articles → solde), puis la demi-journée ou la
  confirmation de l'expédition.
- **Les avis vont à l'équipe de la méthode** (`NotifyDeliveryTeam`) : les livreurs du magasin pour nos camions, les
  usagers qui ont `shipments.manage` pour une expédition.
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
  - l'équipe est avisée après la transaction : « Livraison modifiée » avec le détail, ou « Livraison
    annulée » (« Expédition modifiée » ou « Expédition annulée »). Le paiement à la livraison déjà autorisé ne
    change pas. Une expédition réduite dont tout le reste est en main devient « à faire ».

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

**Propre à l'expédition (#86) :**

- Une expédition est une livraison `method = shipping`, sans date ni demi-journée. Son statut planifié s'affiche
  « À expédier » (`DeliveryStatus::labelFor()`). Le transporteur, l'envoi et la fermeture se font plus tard dans le
  menu d'expédition : les coûts varient d'un transporteur à l'autre, et c'est le commis d'entrepôt qui compare
  les soumissions.
- **Un article expédié doit être payé à 100 %** : il n'y a pas de paiement à la livraison, puisque personne ne peut
  encaisser chez le client. `amountRequiredForShipment()` applique la règle du ramassage aux articles expédiés et
  aux frais d'expédition, qui comptent donc à 100 %, avec le dépôt sur le reste.
- **Frais d'expédition** : un service interne à prix fixe, géré par l'application (`services.code` =
  `shipping_fee`). `ReferenceDataSeeder` le crée, et il ne peut ni passer externe ni être archivé (`isSystem()`).
  Son prix se règle dans la page Services. Le modal propose de l'ajouter à la commande avant de calculer le
  paiement exigé.
- **Dimensions** : l'onglet **Expédition** de la fiche du magasin fixe la plus grande dimension et le poids maximum
  d'un article expédiable (`stores.shipping_max_length`, `shipping_max_weight`, dans l'unité des fiches produits).
  Un article plus grand, plus lourd ou sans dimensions est signalé (`Product::shippingWarningFor()`), sans bloquer
  la demande.
- **À la réception**, une ligne fournisseur attendue pour une expédition l'indique : un avis au moment de la cocher
  dans la nouvelle réception, et un badge dans la réception (`SupplierOrderLine::activeShipment()`).
- **Un seul avis « Nouvelle expédition à faire »**, quand tous les articles sont en main (`ready_at`,
  `NotifyShipmentReady`) : dès la demande si tout est en stock, sinon à la réception du dernier article commandé.
  Il va aux commis d'entrepôt et aux directeurs (`shipments.manage`) et mène à la liste des expéditions de la page
  Livraisons. Un commis peut expédier ce qui est déjà en stock sans attendre l'avis.
- Une même commande peut être livrée, expédiée et ramassée pour des articles différents : les quantités sont
  réservées quelle que soit la méthode.

**Menu du livreur (#94) :**

- La page mobile **Mes livraisons** (`deliveries.mine`, permission `deliveries.handle` : livreurs et directeurs)
  montre les livraisons d'une journée du magasin, par demi-journée. Une livraison qui attend un article encore en
  commande est marquée « En attente d'articles ». L'adresse ouvre l'itinéraire dans l'application de cartes, et le
  montant à percevoir s'affiche pour un paiement à la livraison.
- **L'ordre de la route** (`route_position`) se change avec des flèches, pour la demi-journée. Il est le même pour
  tous les livreurs du magasin (`ReorderDeliveryRoute`). Sans ordre, la liste suit l'ordre de planification.
- **Le chargement** (`CustomerOrder::loadDelivery()`) : la partie chargée de chaque ligne passe « En livraison »,
  séparée au besoin. Elle reste dans `quantity_reserved`, et ses unités passent de « Réservé client » à « En
  livraison » (`DeliveryLoaded`). On ne charge que ce qui est en main pour le client. Un article non chargé est
  retiré de la livraison, et la livraison part « En route ».
- **La remise** (`CustomerOrder::completeDelivery()`, appelée par l'Action `CompleteDelivery`) :
  - la quantité acceptée passe « Livré » et sort de l'inventaire en premier entré, premier sorti
    (`CustomerDelivery`) ;
  - la quantité refusée, avec sa raison, revient en stock client (`DeliveryRefused`), avec le statut qu'avait la
    ligne avant le chargement (`loaded_from_status`). Les vendeurs de la commande en sont avisés ;
  - le paiement exigé doit être perçu avant la remise ;
  - la livraison passe « Livrée ».
- **La signature du client (#93)** viendra avant la remise.
- `CustomerOrder::splitOff()` sépare une ligne pour le ramassage, le chargement et la remise.
  `refreshStatusFromLines()` passe la commande à « Livrée » quand tout est remis et qu'au moins une ligne est
  livrée ou expédiée, même si une autre a été ramassée.

## Conséquences

**Positives**

- La #86 réutilise la vérification d'adresse, les articles, la règle de paiement, les statuts et la
  synchronisation des lignes. Elle n'ajoute que sa propre exigence de paiement, les frais et l'avis à l'entrepôt.
- Une livraison peut être planifiée avant la réception des articles, ce que le papier permettait déjà.

**Négatives / limitations**

- Une livraison dont le client refuse tous les articles est quand même « Livrée » : ses lignes de livraison
  gardent la quantité acceptée (0) et la raison.
- Une livraison en route ne s'annule pas et ne se reporte pas. Un article refusé se replanifie dans une nouvelle
  livraison.
- La capacité compte des livraisons, sans tenir compte de leur durée, de leur distance ni du volume des articles.
- Les frais d'expédition sont ajoutés à la commande dès l'étape des articles, pour pouvoir les encaisser. Si
  l'employé abandonne la demande, ils restent sur la commande jusqu'à ce qu'il les retire.
- Les limites de dimensions n'ont pas d'unité propre : elles suivent celle des fiches produits.

**Protections mises en place**

- Une quantité ne peut pas être prévue deux fois, ni être ramassée en magasin pendant qu'une livraison la prévoit.
- Une livraison ne garde pas une ligne annulée, retirée ou réduite : elle suit la commande, ou elle est annulée.
- Une demi-journée complète ou commencée est refusée. La ligne du magasin est verrouillée pendant la planification.
- Seule une livraison planifiée (pas en route) peut être annulée ou reportée. Si la nouvelle demi-journée est
  refusée, le report est annulé en entier et la livraison d'origine reste planifiée.
- Une expédition ne se reporte pas, et le paiement à la livraison y est refusé, même pour un directeur.
- L'avis « Nouvelle expédition à faire » ne part qu'une fois (`ready_at`).
- On ne charge que ce qui est en main pour le client, et on ne remet qu'une livraison en route, payée.
- `customer_orders.edit` pour planifier, reporter, demander une expédition ou annuler, `deliveries.handle` pour
  le menu du livreur,
  `customer_orders.deliver_unpaid` pour le paiement à la livraison, `stores.edit` pour les camions et les limites
  d'expédition, `shipments.manage` pour les avis d'expédition.

## Questions ouvertes

- Expédier depuis le menu d'expédition (#95) : réutiliser `splitOff()` pour les lignes « Expédié » et compléter
  les frais d'expédition.

## Références

- `database/migrations/2026_10_18_125336_create_delivery_trucks_table.php`
- `database/migrations/2026_10_18_125337_create_customer_order_deliveries_table.php`
- `database/migrations/2026_10_18_133416_add_shipping_to_customer_order_deliveries.php`
- `database/migrations/2026_10_24_193306_add_driver_tracking_to_customer_order_deliveries.php`
- `app/Actions/CompleteDelivery.php`, `app/Actions/ReorderDeliveryRoute.php`, `app/Livewire/Deliveries/Mine.php`
- `app/Actions/RequestShipment.php`, `app/Actions/NotifyShipmentReady.php`, `app/Actions/NotifyDeliveryTeam.php`
- `app/Actions/ScheduleDelivery.php`, `app/Actions/CancelDelivery.php`, `app/Support/DeliveryBooking.php`
- `app/Livewire/CustomerOrders/Deliveries.php`, `app/Livewire/Deliveries/Index.php`
