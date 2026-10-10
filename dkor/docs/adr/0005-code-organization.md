# ADR 0005 — Organisation du code : Livewire mince, modèles riches, Actions pour les processus transverses

- **Statut :** Accepté
- **Date :** 2026-10-10

## Contexte

L'application couvre de nombreux domaines : ventes et commandes clients, commandes fournisseurs,
réceptions, inventaire, catalogue et listes de prix, horaires et rendez-vous, paie et vacances,
comptabilité, usagers et permissions.

Les règles métier sont nombreuses et s'enchaînent. Par exemple, annuler une ligne client touche la
commande fournisseur, l'inventaire, les frais et le solde. Il faut savoir où placer chaque morceau
de code pour :

- retrouver rapidement une règle métier ;
- appliquer la même règle depuis plusieurs écrans ou depuis une tâche planifiée ;
- garder chaque opération cohérente (transaction, validation, permission) ;
- tester les règles sans passer par l'interface.

## Options considérées

### Option A — Contrôleurs MVC classiques et vues Blade

- ✅ Structure Laravel la plus connue
- ❌ Chaque interaction demande une route, un contrôleur et un rechargement de page
- ❌ Interface peu réactive pour un point de vente (recherche, ajout de lignes, glisser-déposer)

### Option B — Toute la logique dans les composants Livewire

- ✅ Rapide à écrire : tout est au même endroit
- ❌ Une même règle se retrouve copiée dans plusieurs composants
- ❌ Impossible de réutiliser la règle dans une commande console
- ❌ Tests couplés à l'interface

### Option C — Couche de services et de repositories, ou dossiers par module (`app/Domain/…`)

- ✅ Séparation stricte des responsabilités
- ❌ Beaucoup de code de plomberie pour une équipe d'une personne
- ❌ S'éloigne des conventions Laravel et des outils (`make:`, Boost, starter kit)

### Option D — Livewire mince, modèles riches et Actions ✔️

La structure Laravel standard, avec une règle claire pour décider où va le code :

- l'écran (Livewire) autorise, appelle et affiche ;
- le modèle porte les opérations sur ses propres données ;
- une Action porte un processus qui dépasse un seul modèle ou qui s'exécute en lot.

- ✅ Reste dans les conventions Laravel
- ✅ Une règle métier existe à un seul endroit et se teste directement
- ❌ Les modèles centraux grossissent (voir Conséquences)

## Décision

Nous retenons l'**option D**.

### Où mettre quoi

| Besoin | Emplacement | Exemple |
|---|---|---|
| Une page de l'application | `app/Livewire/<Domaine>/` (composant pleine page, branché directement dans `routes/web.php`) | `CustomerOrders/Show` |
| Un formulaire réutilisé dans plusieurs pages | `app/Livewire/<Nom>Form.php` | `CustomerForm`, `ProductForm` |
| Une opération sur un agrégat et ses lignes | Méthode publique du modèle racine | `CustomerOrder::cancelLineWithFee()` |
| Un processus transverse, un calcul complexe ou un traitement par lots | `app/Actions/<Verbe><Nom>.php`, méthode `handle()` | `ImportPriceListCsv`, `CalculateVacationBalance` |
| Une tâche planifiée | `app/Console/Commands/`, planifiée dans `routes/console.php` ; elle délègue à une Action | `ApplyPriceLists` → `ApplyPriceListItems` |
| Un statut ou une liste fermée de valeurs | `app/Enums/` | `CustomerOrderLineStatus` |
| Un comportement partagé entre classes | `app/Concerns/` (trait) | `HasSearchName`, `SearchesCustomers` |
| Un paramètre métier (taux, pourcentages) | `config/<domaine>.php`, avec une variable d'environnement si le paramètre change selon le déploiement | `config/sales.php` |
| Un paramètre propre à un magasin | Colonne de la table `stores`, modifiable dans sa fiche | `cancellation_fee_percent` |
| Rôles et permissions par défaut | `config/access.php` (lu par les seeders) | `customer_orders.edit` |

### Conventions

- **Les composants Livewire n'ont pas de règles métier.** Une méthode d'action fait trois choses :
  `$this->authorize('…')`, appel du modèle ou de l'Action, puis affichage du résultat ou de l'erreur
  avec `Flux::toast()`.
- **Une règle métier violée lance une `DomainException`** avec un message en français destiné à
  l'usager. Le composant l'attrape et l'affiche ; il ne revérifie pas la règle lui-même.
- **Une opération qui modifie plusieurs enregistrements s'exécute dans `DB::transaction()`**. On
  ajoute `lockForUpdate()` sur les lignes relues quand deux postes peuvent agir en même temps sur
  la même commande (comme dans `CustomerOrder`).
- **Les pages Livewire sont regroupées par domaine** et nommées `Index`, `Show`, `Create` ou d'après
  leur rôle (`Templates`, `Holidays`). Chaque page a sa vue Blade dans
  `resources/views/livewire/<domaine>/`.
- **Pas de contrôleurs HTTP** : les routes pointent directement vers les composants Livewire.
- **Langue :** le code (classes, méthodes, colonnes) est en anglais ; les PHPDoc et les textes de
  l'interface sont en français, ces derniers passant par `__()`.

## Conséquences

**Positives**

- Une règle comme le calcul du frais d'annulation existe à un seul endroit et sert à la fois à
  l'aperçu (vue) et à l'annulation (modèle).
- Les tâches planifiées réutilisent exactement la logique des écrans.
- Les règles métier peuvent se tester en appelant directement le modèle ou l'Action. Aujourd'hui,
  la plupart des tests de `tests/Feature/` passent toutefois par `Livewire::test()`.

**Négatives / limitations**

- Les modèles racines grossissent : `CustomerOrder` dépasse 1 200 lignes et `SupplierOrder` près
  de 900. Le composant `CustomerOrders/Show` approche les 1 000 lignes.
- La frontière entre « opération du modèle » et « Action » demande du jugement.

**Protections mises en place**

- Double contrôle d'accès : `middleware('can:…')` sur la route, puis `$this->authorize()` dans
  chaque méthode Livewire qui modifie des données (voir [ADR 0001](0001-permissions.md)).
- `PagePermissionsTest` vérifie l'accès aux pages et `ActionPermissionsTest` vérifie l'accès
  aux actions.

## Questions ouvertes

- **Seuil d'extraction :** à partir de quand une méthode d'un gros modèle devient-elle une Action ?
  Piste : extraire quand l'opération touche un autre agrégat (commande fournisseur, inventaire) ou
  dépasse une cinquantaine de lignes.
- **Découper `CustomerOrders/Show`** en sous-composants (paiements, lignes, ramassages), ou en
  formulaires Livewire (`Form` objects).

## Références

- `livewire/livewire` ^4.1, `livewire/flux` ^2.13
- `routes/web.php`, `routes/console.php`
- `app/Models/CustomerOrder.php`, `app/Actions/`
