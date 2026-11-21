# ADR 0019 — Remboursements clients : mode de paiement d'origine ou crédit au compte

- **Statut :** Accepté
- **Date :** 2026-11-15
- **Complète :** [ADR 0008](0008-money-in-cents.md) (montants en cents) et [ADR 0016](0016-ledger-postings.md)
  (écritures des paiements et remboursements)

## Contexte

Une commande client se rembourse à trois endroits :

- au **retour** d'un article remis au client (`CustomerOrder::returnLine()`, option « Rembourser ») ;
- au **retour d'un défectueux** remboursé (`CustomerOrder::returnDefective()`) ;
- quand on **rembourse le crédit** de la commande (`CustomerOrder::refundCredit()`).

Jusqu'ici, chaque remboursement offrait tous les modes de paiement actifs, et le modèle ne vérifiait que le total.
On pouvait donc rembourser en comptant un client qui avait payé par carte, ou en Visa un client qui avait payé
comptant. C'est une erreur grave :

- de l'argent sort du magasin par un mode qui n'en a jamais reçu : la caisse ou le dépôt de la carte ne balance plus ;
- c'est la porte ouverte à la fraude, par exemple un achat par carte volée remboursé en argent comptant ;
- les règles des émetteurs de cartes exigent de rembourser sur la carte qui a payé.

## Options considérées

### Option A — Avertir sans bloquer

Le modal signale le mode d'origine, mais l'employé peut choisir un autre mode.

- ✅ Souple pour les cas rares (carte expirée, client qui insiste)
- ❌ La règle dépend de l'attention de l'employé : l'erreur et la fraude restent possibles

### Option B — Mode d'origine ou crédit au compte, imposé ✔️

Un remboursement se fait sur un mode de paiement qui a payé la commande, sans dépasser ce qu'il a payé, ou au crédit
du compte du client.

- ✅ L'argent ressort par où il est entré : caisse et dépôts balancent
- ✅ Le crédit au compte règle les cas où le mode d'origine n'est plus possible
- ❌ Un remboursement en argent d'un paiement par carte n'est plus possible, même à bon droit

## Décision

Nous retenons l'**option B**, **sans exception** : aucune permission, même de gestionnaire, ne permet de rembourser
sur un autre mode. C'est le mode de paiement d'origine ou le crédit au compte du client.

- **Montant remboursable par mode :** `CustomerOrder::refundableByMethod()` donne, pour chaque mode de paiement, ce
  que le client a payé avec ce mode sur la commande (`Payment`), moins ce qui lui a déjà été remboursé avec ce mode
  (`Refund`).
- **Garde dans le modèle :** les trois remboursements passent par `CustomerOrder::recordRefunds()`. Le remboursement
  est refusé si un mode n'a pas payé la commande (« Le client n'a rien payé en Visa sur cette commande… ») ou si son
  total dépasse ce que ce mode a payé. Plusieurs lignes du même mode s'additionnent avant la vérification.
- **Crédit au compte du client :** un remboursement sans mode (`method_id` vide) va au compte du client. Il est
  enregistré en paiement `CreditTransfer` négatif et ajouté à `customers.credit_balance`, comme « Porter au compte du
  client » (`transferCreditToCustomer()`). Le client l'utilise plus tard à un ramassage (`CreditUse`).
- **Grand livre :** les crédits au compte des clients ont leur propre compte, **Crédits de clients** (2410, passif,
  `LedgerAccount::CUSTOMER_CREDITS`). Un crédit porté au compte passe des Dépôts de clients aux Crédits de clients ; un
  crédit utilisé fait l'inverse (`CustomerOrder::postCustomerCreditEntry()`, ADR 0016). L'invariant I16 vérifie que
  le solde du compte égale la somme des `customers.credit_balance`.
- **Mode désactivé :** un mode d'origine désactivé depuis le paiement sert encore à rembourser. Seul un nouveau
  paiement exige un mode actif.
- **Interface :** dans les modals (`App\Livewire\CustomerOrders\Show`, `getRefundMethods()`), le menu n'offre que les
  modes qui ont encore un montant remboursable, chacun avec son maximum (ex. « Visa (jusqu'à 120.00 $) »), puis
  « Crédit au compte du client » (`Show::STORE_CREDIT`). Le mode proposé par défaut est celui qui a payé le plus. La
  validation refuse toute autre valeur.

## Conséquences

**Positives**

- La caisse et les comptes de chaque mode de paiement balancent avec les remboursements.
- La règle est écrite une seule fois, dans le modèle : un nouveau chemin de remboursement l'hérite en passant par
  `recordRefunds()`.

**Négatives / limitations**

- Un client qui a payé par carte ne peut pas être remboursé en argent comptant, même si sa carte est expirée : il
  reçoit un crédit au compte. C'est voulu : il n'y a pas d'exception.
- La règle se vérifie commande par commande : un crédit au compte utilisé sur une autre commande ne se rembourse pas
  sur le mode qui l'a payé à l'origine.

**Protections mises en place**

- Le modèle refuse un mode qui n'a pas payé la commande et un montant plus grand que ce qu'il a payé, quel que soit
  l'écran d'origine.
- Le modal n'offre que les modes permis et valide le choix contre cette liste.
- `php artisan ledger:check` (I16) compare le compte Crédits de clients aux crédits au compte des clients.
- `CustomerOrderReturnsTest` vérifie le refus d'un mode non utilisé, le refus d'un dépassement, les choix offerts
  dans le modal et le remboursement au crédit du compte. `CustomerSalesPostingsTest` vérifie les écritures du crédit
  porté au compte puis utilisé.

## Références

- `app/Models/CustomerOrder.php` (`refundableByMethod()`, `recordRefunds()`, `returnLine()`, `returnDefective()`,
  `refundCredit()`, `transferCreditToCustomer()`)
- `app/Models/CustomerOrder.php` (`postCustomerCreditEntry()`), `app/Models/LedgerAccount.php` (`CUSTOMER_CREDITS`),
  `app/Support/LedgerChecks.php` (I16)
- `app/Livewire/CustomerOrders/Show.php` (`getRefundMethods()`, `STORE_CREDIT`)
- `resources/views/livewire/customer-orders/show.blade.php` (modals de retour, de défectueux et de crédit)
- `tests/Feature/CustomerOrderReturnsTest.php`, `tests/Feature/CustomerSalesPostingsTest.php`
