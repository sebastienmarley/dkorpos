# ADR 0008 — Montants en cents (entiers) et objet `Money`

- **Statut :** Accepté
- **Date :** 2026-10-11
- **Issue liée :** #76

## Contexte

Les montants sont stockés en `decimal`, castés en `float` dans les modèles, et calculés en virgule
flottante. Deux problèmes en découlent.

1. **Les calculs se trompent d'un cent.** En virgule flottante, 2,05 × 30 / 100 vaut 0,61499999…, au lieu
   de 0,615. L'arrondi donne donc 0,61 $ au lieu de 0,62 $. Une vérification systématique des
   pourcentages appliqués (30 %, 15 %, 2 %, 12,5 % sur les montants de 0,01 $ à 1 000 $) trouve
   **125 écarts sur 400 000 calculs**. Les dépôts, les frais d'annulation et les escomptes sont touchés.
   Les tests témoins le reproduisent dans l'application.
2. **SQLite stocke les `decimal` en virgule flottante** ([ADR 0006](0006-database.md)) : même les
   sommes faites par la base sont imprécises.

À l'inventaire, l'application compte 60 colonnes décimales. 36 sont des montants et 24 des taux ou des
mesures. Le code compte 51 casts `float` dans les modèles, 83 `round(`, 79 conversions `(float)` et
144 `number_format`.

## Options considérées

### Option A — Montants en cents, cast vers `float` (dollars)

- ✅ Peu de code à changer
- ❌ Les calculs restent en virgule flottante : le problème n'est pas réglé

### Option B — Montants en cents, manipulés comme de simples `int`

- ✅ Calculs exacts
- ❌ **Un oubli passe en silence** : 1234 cents affiché comme « 1234.00 $ » au lieu de « 12.34 $ »
- ❌ Chaque calcul refait ses propres arrondis

### Option C — Montants en cents, objet `Money` maison et `MoneyCast` ✔️

- ✅ Calculs exacts, et règles d'arrondi écrites une seule fois
- ✅ **Un oubli est bruyant** : `$money * 2` ou `number_format($money)` échoue, à l'analyse (PHPStan)
  ou au rendu (tests)
- ✅ Aucune dépendance ajoutée
- ❌ Gros chantier : tous les modules qui manipulent de l'argent

### Option D — Paquet `brick/money`

- ✅ Bibliothèque éprouvée, multidevise
- ❌ Nouvelle dépendance, plus large que le besoin (une seule devise de vente)

## Décision

Nous retenons l'**option C**.

### Stockage

- Un montant est un **entier signé en cents** (`integer`). Les montants négatifs existent :
  remboursements, écarts de facture, ajustements d'arrondi.
- **Les noms de colonnes ne changent pas** (`unit_price`, `total`…). Le cast en `Money` rend toute
  utilisation oubliée bruyante, ce qui rend un suffixe `_cents` inutile.
- Chaque migration convertit les données existantes (`ROUND(valeur * 100)`), et son `down()` fait
  l'inverse.

### Objet `Money`

Un objet immuable, en une seule devise (le dollar canadien des ventes), qui contient un nombre entier
de cents. Opérations prévues :

| Besoin | Méthode |
|---|---|
| Créer | `Money::of(int $cents)`, `Money::zero()`, `Money::parse('12,34')` (saisie d'un formulaire) |
| Lire | `cents(): int`, `toDecimal(): string` (« 12.34 »), `format(): string` (« 12.34 $ ») |
| Calculer | `plus()`, `minus()`, `times(int $quantity)`, `negate()`, `sum(iterable)` |
| Pourcentage, taux | `percent(string\|float $rate)`, `multiply(string\|float $factor)` : arrondis au cent |
| Comparer | `equals()`, `isZero()`, `isNegative()`, `greaterThan()`, `lessThan()` |

- **`MoneyCast`** lit l'entier de la base et le transforme en `Money`. À l'écriture, il accepte seulement un
  `Money` ou `null` : un `float` est refusé.
- Les **propriétés Livewire** des formulaires restent des chaînes. `Money::parse()` les convertit au
  moment d'enregistrer.
- L'**affichage** reste au format actuel (« 12.34 $ »). Passer au format québécois (« 12,34 $ ») est
  une autre décision.

### Règles d'arrondi

| Calcul | Règle |
|---|---|
| Prix × quantité, sommes, différences | Exact, sans arrondi |
| Pourcentage ou taux appliqué à un montant (taxe, dépôt, frais, escompte, rabais de liste de prix) | Arrondi une seule fois au cent, **demie arrondie vers le haut** (en s'éloignant de zéro) |
| Taxes d'une commande | Arrondies **par ligne de taxe** ; une taxe en cascade s'applique au montant plus les taxes individuelles |
| Coût × multiplicateur du fournisseur | Arrondi au cent, puis tranches du prix de vente : x,99 $ sous 20 $, dollar supérieur jusqu'à 100 $, puis se termine par 4 ou 9. Une pièce qui ne coûte rien reste à 0 $ |
| Paiement comptant | Arrondi aux 0,05 $ près (1-2 ¢ vers le bas, 3-4 ¢ vers le haut) |

### Ce qui reste en `decimal`

Taux, pourcentages, multiplicateurs et mesures ne sont pas des montants : ils restent en `decimal`.

| Table | Colonnes |
|---|---|
| `products`, `price_list_items` | `length`, `width`, `height`, `weight` |
| `suppliers` | `price_multiplier`, `base_multiplier`, `customs_fee` et `shipping_fee` (composantes du multiplicateur, pas des montants), `early_payment_discount_percent` |
| `stores` | `cancellation_fee_percent`, `custom_deposit_percent` |
| `users` | `commission_rate`, `hours_per_day`, `vacation_days_accrued`, `vacation_hours_available` |
| Autres | `currencies.rate`, `taxes.rate`, `customer_order_taxes.rate`, `price_list_lists.discount_percent`, `supplier_invoices.discount_percent` |

### Les 36 montants, et une PR par module

Les modules suivent l'ordre des dépendances : le coût d'un produit alimente la commande fournisseur, et
son prix de vente alimente la commande client.

| PR | Module | Colonnes |
|---|---|---|
| 1 | **Fondation** | `Money`, `MoneyCast`, `Money::parse()`, adaptation de `x-cost-input`, tests unitaires (aucune colonne) |
| 2 | **Catalogue, services et pièces** (8) | `products.cost`, `products.imap`, `price_list_items.cost`, `price_list_items.imap`, `services.selling_price`, `service_supplier.cost`, `service_supplier.selling_price`, `parts.last_cost` |
| 3 | **Fournisseurs, réceptions, inventaire et factures** (13) | `suppliers.prepaid_amount`, `supplier_order_lines.unit_cost`, `reception_lines.unit_cost`, `inventory_units.cost`, `supplier_invoices.merchandise_total`, `.freight_fee`, `.customs_fee`, `.taxes`, `.computed_total`, `.invoice_total`, `.variance`, `.discount_amount`, `supplier_invoice_lines.unit_cost` |
| 4 | **Ventes** (13) | `customer_orders.subtotal`, `.total`, `.amount_paid`, `.balance_due`, `customer_order_lines.unit_price`, `.unit_cost`, `.cancellation_fee`, `customer_order_payments.amount`, `.rounding_adjustment`, `.cash_tendered`, `.change_given`, `customer_order_taxes.amount`, `customers.credit_balance` |
| 5 | **RH** (2) | `users.hourly_rate`, `users.weekly_salary` |
| 6 | **Verrouillage** | Test de garde sur le schéma, ménage final, mise à jour de l'ADR 0006 et du modèle de données |

Chaque PR est complète pour son module : migration des données, casts, PHPDoc (`@property Money`),
calculs, formulaires, vues et tests. Elle est livrée avec **PHPStan niveau 7 à 0 erreur** et **la suite
complète au vert**. Entre deux modules, les conversions temporaires sont marquées `@todo #76` et retirées
au module qui les rend inutiles.

## Conséquences

**Positives**

- Calculs exacts au cent, y compris les sommes faites par la base (`SUM` sur des entiers).
- Une seule définition des règles d'arrondi.
- Un montant oublié est signalé par PHPStan ou par les tests, au lieu de s'afficher faux.

**Négatives / limitations**

- Gros chantier qui touche presque tous les modules et beaucoup de tests (environ 180 assertions sur
  des montants en virgule flottante).
- Pendant le chantier, des conversions temporaires entre modules.
- Les montants affichés sans passer par `format()` (exports, courriels) doivent être revus un par un.

**Protections mises en place**

- **Tests témoins** (`tests/Feature/MoneyGoldenTest.php`) : montants exacts calculés à la main (taxes,
  dépôt, comptant, frais d'annulation, remboursement, tranches du prix de vente, facture fournisseur),
  comparés en cents. Pendant le chantier, **seule leur mise en place peut changer, jamais les valeurs
  attendues**. Deux d'entre eux reproduisent les écarts d'un cent : ils sont ignorés jusqu'à la PR Ventes,
  qui doit les réactiver.
- **PHPStan niveau 7** (0 erreur avant le chantier) repère les calculs oubliés dans le code PHP.
- **Test de garde sur le schéma** (PR 6) : il échoue si une colonne `decimal` n'est pas dans la liste
  des taux et mesures permis.

## Questions ouvertes

- **Emplacement de `Money` :** `app/Casts/` (créé par `php artisan make:cast`) pour le cast, mais où mettre
  l'objet ? Proposition : `app/Support/Money.php`. C'est un nouveau dossier, à approuver.
- **Format d'affichage** québécois (« 12,34 $ ») : décision séparée, facile à appliquer ensuite grâce à
  `format()`.

## Références

- Issue #76
- `tests/Feature/MoneyGoldenTest.php`
- [ADR 0005](0005-code-organization.md) : organisation du code
- [ADR 0006](0006-database.md) : SQLite et stockage des décimaux
