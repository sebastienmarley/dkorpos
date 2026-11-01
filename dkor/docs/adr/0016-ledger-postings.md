# ADR 0016 — Écritures du grand livre par document

- **Statut :** Accepté
- **Date :** 2026-11-01
- **Issue liée :** #131 (et #132 pour les paiements aux fournisseurs)
- **Complète :** [ADR 0011](0011-inventory-movements-and-adjustments.md) (grand livre, couches de coût FIFO) et
  [ADR 0012](0012-inventory-by-store.md) (stock par magasin)

## Contexte

L'[ADR 0011](0011-inventory-movements-and-adjustments.md) a créé le grand livre (`ledger_accounts`,
`ledger_entries`, `ledger_entry_lines`, `LedgerEntry::post()`), mais seuls les ajustements d'inventaire et les
factures de transporteur y passent des écritures. Le solde du compte Stock n'égale donc pas la valeur de
l'inventaire, et la comptable refait chaque mois, à la main, les achats non facturés, les ventes de produits et de
services, les taxes à remettre et la conciliation des comptes bancaires CAD et USD (#131).

Plusieurs règles manquent pour que chaque document passe son écriture :

1. **Le moment.** Une vente se comptabilise-t-elle à la commande, au paiement ou à la livraison ? Une réception
   avant ou après sa facture ?
2. **La valeur.** Le coût d'une unité change à la facture (`SupplierInvoice::record()` reporte le coût réel sur les
   unités) : une écriture déjà passée ne suit pas. Les frais de transport et de douane ne sont imputés nulle part.
3. **La devise.** Les coûts d'un fournisseur américain sont en USD (`Supplier::exchangeRate()`, `Currency.rate` :
   CAD par unité de devise, ex. : USD = 1,35). Le grand livre est en CAD.
4. **Les taxes.** Une facture de marchandises ne garde qu'un total de taxes (`supplier_invoices.taxes`) : on ne
   peut pas réclamer la TPS et la TVQ séparément. Les taxes perçues (`customer_order_taxes`) n'ont pas de compte.
5. **La clôture.** Une fois le mois remis à la comptable, rien n'empêche une écriture datée dans ce mois.

## Options considérées

### Option A — Écritures générées par un rapport, en fin de période

Un rapport relit les documents de la période et calcule les écritures à la demande.

- ✅ Aucun changement dans les documents
- ❌ Un document modifié après coup change silencieusement un mois déjà remis
- ❌ Pas de lien entre une écriture et son document, pas d'invariant vérifiable en continu

### Option B — Export des documents vers le logiciel de la comptable

- ✅ La comptable garde ses outils
- ❌ Elle refait les écritures : c'est le travail que #131 veut éviter
- ❌ Dépend d'un format d'import propre à un logiciel

### Option C — Une écriture par événement de document, dans sa transaction ✔️

Chaque document passe son écriture au moment où il change la situation financière, par `LedgerEntry::post()`, dans
la même transaction que le document. Une correction passe une écriture inverse.

- ✅ Un document et son écriture ne peuvent pas diverger (même transaction)
- ✅ Chaque écriture est liée à son document (référence polymorphe) : la comptable remonte à la source
- ✅ Les soldes se vérifient en continu par des invariants (Stock, Achats non facturés, Dépôts de clients)
- ❌ Chaque document doit être touché ; un document oublié laisse un trou

## Décision

Nous retenons l'**option C**. Les règles de l'[ADR 0011](0011-inventory-movements-and-adjustments.md) restent :
`LedgerEntry::post()` est le seul point de création, une écriture est équilibrée, immuable et porte son magasin
quand le document en a un.

### Les comptes système

Ajoutés à `LedgerAccount::systemAccounts()` et créés par `ReferenceDataSeeder`. Le comptable ajuste le code et le
nom, pas la clé.

| Clé | Code | Nom | Type |
|---|---|---|---|
| `cash_cad` | 1010 | Encaisse CAD | Actif |
| `cash_usd` | 1020 | Encaisse USD | Actif |
| `accounts_receivable` | 1200 | Comptes clients | Actif |
| `supplier_credits_receivable` | 1250 | Crédits à recevoir des fournisseurs | Actif |
| `goods_received_not_invoiced` | 2110 | Achats non facturés | Passif |
| `customer_deposits` | 2400 | Dépôts de clients | Passif |
| `merchandise_sales` | 4000 | Ventes de marchandises | Produit |
| `service_sales` | 4100 | Ventes de services | Produit |
| `cancellation_fees` | 4200 | Frais d'annulation | Produit |
| `cost_of_goods_sold` | 5000 | Coût des marchandises vendues | Charge |
| `cost_of_services_sold` | 5010 | Coût des services vendus | Charge |
| `invoice_variance` | 5130 | Écarts de facturation fournisseurs | Charge |
| `cash_rounding` | 5900 | Arrondis au comptant | Charge |
| `purchase_discounts` | 5050 | Escomptes sur achats (#132) | Charge (solde créditeur) |
| `exchange_gain_loss` | 5800 | Gain/perte de change (#132) | Charge |

- Un compte « à payer » par taxe perçue, créé au besoin comme `forTaxReceivable()` : `forTaxPayable()`, clé
  `tax_payable:<taxe>`, codes 2310, 2311…
- Chaque mode de paiement client (`customer_payment_methods`) est associé à un compte d'encaisse ou de
  compensation (`ledger_account_id`), par défaut Encaisse CAD.

### Les achats

| Événement | Débit | Crédit | Date |
|---|---|---|---|
| Réception terminée (`Reception::complete()`) | Stock (coût des unités reçues, en CAD) | Achats non facturés | Réception |
| Renversement de réception (`SupplierOrder::reverseReceipt()`) | Achats non facturés | Stock | Renversement |
| Facture de réception (`SupplierInvoice::record()`) | Achats non facturés (montant passé à la réception) ; Stock ou Coût des marchandises vendues (écart de coût, transport, douane) ; Crédits à recevoir des fournisseurs (`credit_requested`) ; une taxe à recevoir par taxe ; Écarts de facturation (`variance`) | Comptes fournisseurs (`invoice_total`) | Facture |
| Note de crédit reçue (`recordCreditNote()`) | Comptes fournisseurs | Crédits à recevoir des fournisseurs | Note de crédit |
| Facture de services (`recordForOrder()`) | Coût des services vendus ; taxes à recevoir | Comptes fournisseurs | Facture |
| Facture sans réception (`recordStandalone()`) | Compte de charge choisi à la saisie ; taxes à recevoir | Comptes fournisseurs | Facture |
| Facture de transporteur (`recordForShipments()`) | Inchangée ([ADR 0011](0011-inventory-movements-and-adjustments.md)) | | |

- **La réception est au coût de la commande**, converti en CAD au taux de la devise du jour. L'écriture égale la
  valeur des unités créées (`inventory_movements.value`).
- **La facture corrige le coût.** L'écart entre le coût facturé et le coût reçu va au **Stock** pour les unités
  encore ouvertes et au **Coût des marchandises vendues** pour celles déjà sorties. Ainsi le compte Stock égale
  toujours la valeur FIFO des unités ouvertes.
- **Transport et douane** (`freight_fee`, `customs_fee`) sont **capitalisés** : répartis sur les unités de la
  réception au prorata de leur coût, ajoutés à `inventory_units.cost`, et traités comme l'écart de coût. Le coût
  de la ligne de commande (`unit_cost`) reste le prix du fournisseur : il sert aux prochaines commandes.
- **Les taxes de la facture sont détaillées par taxe** (`supplier_invoice_taxes`, déjà utilisée par les factures de
  transporteur), pour réclamer chaque crédit de taxe séparément. Le total `taxes` devient la somme des lignes.
- **Un renversement n'est permis qu'avant la facture** (règle actuelle de `reverseReceipt()`) : il n'a donc qu'à
  inverser la réception.

### Les ventes

La vente se comptabilise **à la livraison ou au ramassage** d'une ligne, jamais à la création de la commande.
Avant, l'argent reçu est un dépôt.

| Événement | Débit | Crédit | Date |
|---|---|---|---|
| Paiement reçu (`CustomerPaymentType::Payment`) | Compte du mode de paiement | Dépôts de clients | Paiement |
| Remboursement (`Refund`) | Dépôts de clients | Compte du mode de paiement | Remboursement |
| Arrondi au comptant (`rounding_adjustment`) | Arrondis au comptant (ou l'inverse) | Compte du mode de paiement | Paiement |
| Ligne livrée, ramassée, expédiée ou service complété | Dépôts de clients, puis Comptes clients pour ce qui n'est pas payé | Ventes de marchandises (produit, pièce) ou Ventes de services ; une taxe à payer par taxe | Remise |
| Sortie des unités (même événement) | Coût des marchandises vendues | Stock (valeur FIFO des unités sorties) | Remise |
| Retour client | Inverse des deux écritures précédentes, pour la quantité retournée, au coût des unités rouvertes | | Retour |
| Frais d'annulation | Dépôts de clients (ou Comptes clients) | Frais d'annulation ; taxes à payer | Annulation |

- **Les ventes se passent par écart cumulatif** (`CustomerOrder::postSales()`, appelée par `recalculateBalance()`
  après chaque changement de la commande) : on calcule ce que la commande doit avoir comptabilisé (lignes remises,
  frais d'annulation, taxes sur ce cumul, coût net des mouvements d'inventaire qui la référencent, partage entre
  Dépôts et Comptes clients selon le payé) et on passe l'écart avec ce que ses écritures ont déjà passé. Un seul
  point d'accroche couvre le ramassage, la livraison, l'expédition, le service complété, les retours, les
  annulations avec frais et les paiements qui soldent un compte client.
- **Les taxes** sont recalculées sur le cumul remis : la somme des écritures égale les taxes de la commande une
  fois tout remis, sans répartition au prorata.
- Une ligne **sans frais** (`is_no_charge`) a un total nul : seule la sortie des unités passe.
- **Le coût des services et des pièces** passe à la facture du fournisseur (Coût des services vendus ou des
  marchandises vendues), pas à la remise : les pièces n'ont pas d'unités d'inventaire et le coût d'un service
  n'est connu qu'à sa facture. Le compte Achats non facturés ne porte donc que les réceptions de marchandises.
- Le transfert et l'utilisation de crédit (`CreditTransfer`, `CreditUse`) déplacent un solde entre commandes dans
  Dépôts de clients : ils ne passent aucune écriture.
- Le magasin de l'écriture est celui de la commande (`customer_orders.store_id`).
- Chaque mode de paiement client a un compte (`customer_payment_methods.ledger_account_id`), choisi dans l'écran
  des modes de paiement ; sans compte, Encaisse CAD.

### La devise

- Le grand livre est en **CAD**. Un document en devise est converti au **taux de la devise (`Currency.rate`) au
  moment de la saisie**, et ce taux est **figé sur le document** (`supplier_invoices.exchange_rate`, avec le montant
  en devise). Changer le taux de la devise ensuite ne touche ni le document ni son écriture.
- La réception est convertie au taux du jour de la réception ; la facture, au sien. L'écart passe par la
  correction de coût de la facture (Stock ou Coût des marchandises vendues).
- Le gain ou la perte de change apparaît au **paiement** de la facture (#132) : Comptes fournisseurs est débité au
  taux de la facture, l'encaisse au montant réellement payé, l'écart va au compte Gain/perte de change.

### Les paiements aux fournisseurs (#132)

| Événement | Débit | Crédit |
|---|---|---|
| Paiement | Comptes fournisseurs (au taux de la facture) | Encaisse CAD ou USD ; Escomptes sur achats (escompte appliqué) ; Gain/perte de change (écart, ou au débit) |
| Annulation du paiement | Inverse | |

### Les écritures manuelles

Ce qui n'a pas de document dans l'application passe par le **journal général** (`journal_entries`, page Journal
général, permission `ledger.journal`) : soldes d'ouverture (contre Solde d'ouverture), dépôts des processeurs de
paiement (Encaisse et frais contre le compte de compensation du mode de paiement), virements entre encaisses, frais
bancaires, paie. Chaque écriture manuelle passe une écriture équilibrée qui la référence (`RecordJournalEntry`);
elle ne se modifie pas, elle se renverse (`ReverseJournalEntry`, une seule fois). Le comptable ajoute les comptes
dont il a besoin (frais bancaires, salaires…) dans la page Grand livre (`ledger.edit`); leur type ne change plus.

### La clôture

- Table `ledger_closings` : historique des clôtures et réouvertures; la dernière ligne donne la date jusqu'à
  laquelle le grand livre est clôturé (`closed_through`, vide : tout ouvert). Permission `ledger.close` pour
  clôturer (une période terminée, après la clôture actuelle) et rouvrir (la clôture recule à la veille de la date
  rouverte), dans la page États comptables.
- `LedgerEntry::post()` ne refuse rien : une écriture datée dans une période clôturée est **reportée au premier
  jour de la période ouverte**, et sa description garde la date du document. Le document lui-même garde sa date.

### Les contrôles

Nouveaux invariants, vérifiés par des tests et par la commande `php artisan ledger:check` (`LedgerChecks`), qui
échoue au premier écart :

| # | Invariant |
|---|---|
| I11 | Solde de Stock = somme des coûts des unités ouvertes. |
| I12 | Solde d'Achats non facturés = valeur nette des unités reçues des réceptions sans facture. |
| I13 | Dépôts de clients moins Comptes clients = montants payés moins ventes comptabilisées, sur toutes les commandes, plus les crédits au compte des clients. |
| I14 | Solde de chaque taxe à payer ou à recevoir = taxes des documents comptabilisés (vérifié par les tests seulement). |
| I15 | Solde de Comptes fournisseurs = total CAD des factures au grand livre, moins les notes de crédit reçues et les paiements actifs (#132). |

### Les états

La page **États comptables** (`ledger.view`, `LedgerReport`) donne, sur une période et pour un magasin ou
l'ensemble : la balance de vérification (ouverture, débits, crédits, clôture par compte), le grand livre d'un compte
(solde courant, lien vers le document source) et les taxes perçues, payées et nettes par taxe, avec les numéros
d'inscription. La balance et le détail des écritures s'exportent en CSV (point-virgule, virgule décimale).

## Conséquences

**Positives**

- La comptable reçoit des comptes déjà ventilés : achats non facturés, ventes de produits et de services, taxes
  perçues et payées, encaisses par devise.
- Le compte Stock égale la valeur de l'inventaire, ce qui règle la limitation de l'ADR 0011.
- Chaque écriture remonte à son document ; aucun mois remis ne change après la clôture.

**Négatives / limitations**

- Beaucoup de points d'accroche (réception, renversement, factures, livraison, ramassage, retours, paiements,
  annulations). Un document oublié fausse un invariant : les tests d'invariants le détectent.
- La facture qui corrige le coût doit savoir quelles unités sont sorties ; la valeur des mouvements de sortie déjà
  consignés (`inventory_movements.value`) ne suit pas.
- Le calcul cumulatif des ventes porte sur toute la commande : une commande remise avant la mise en service
  passerait toutes ses ventes passées à sa prochaine modification. La mise en service part d'une base sans
  commande remise : aucune date de départ n'est prévue.
- Les documents passés avant la mise en service n'ont pas d'écriture : il faut un solde d'ouverture par compte.

**Protections mises en place**

- Chaque écriture est passée dans la transaction de son document : l'un ne va pas sans l'autre.
- Une valeur nulle ne passe pas d'écriture (règle de l'ADR 0011).
- Les taux de change sont figés sur les documents.
- La clôture reporte au lieu de modifier : aucune écriture n'est jamais datée dans une période clôturée.

## Questions ouvertes

- **Solde d'ouverture** : un écran pour saisir les soldes de départ de chaque compte, ou un import.
- **Réévaluation** des factures USD non payées en fin de période (gain/perte non réalisé).
- **Transferts entre magasins** : une écriture par magasin si les états financiers deviennent par magasin.
- **Dossier défectueux** : la marchandise retournée au fournisseur (crédit Stock, débit Crédits à recevoir).

## Références

- [ADR 0008](0008-money-in-cents.md) — montants en cents
- [ADR 0011](0011-inventory-movements-and-adjustments.md) — grand livre et couches de coût FIFO
- [ADR 0012](0012-inventory-by-store.md) — stock par magasin
- `app/Models/LedgerEntry.php`, `app/Models/LedgerAccount.php`, `app/Models/SupplierInvoice.php`,
  `app/Models/Reception.php`
