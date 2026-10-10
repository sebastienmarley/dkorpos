# ADR 0006 — Base de données : SQLite, schéma portable et données historiques protégées

- **Statut :** Accepté
- **Date :** 2026-10-10

## Contexte

L'application gère des données dont la valeur est surtout historique : commandes, paiements,
factures fournisseurs, réceptions, mouvements d'inventaire, horaires et paie. Ces données servent de
preuve (reçus, comptabilité, litiges) et ne doivent pas changer ni disparaître par accident.

En même temps, le projet est développé par une seule personne, sur Laravel Herd, avec un schéma qui
évolue presque chaque jour (plus de 120 migrations). Il faut :

- un moteur simple à installer, à réinitialiser et à tester ;
- des conventions de schéma claires pour les montants, les statuts, les clés étrangères et la
  suppression ;
- garder la porte ouverte à un autre moteur en production.

## Options considérées

### Option A — SQLite ✔️

- ✅ Aucun serveur à installer : un seul fichier, fonctionne directement avec Herd
- ✅ Tests rapides dans une base en mémoire (`:memory:`), recréée à chaque test
- ✅ Même moteur en développement et en test
- ❌ Un seul écrivain à la fois : les écritures concurrentes attendent leur tour
- ❌ `lockForUpdate()` est ignoré : c'est le verrou global de la base qui protège les transactions
- ❌ Pas de vrai type décimal : les colonnes `decimal` sont stockées en nombres à virgule flottante

### Option B — MySQL / MariaDB

- ✅ Standard de l'hébergement Laravel, verrous par ligne, vrai type `DECIMAL`
- ❌ Un service de plus à faire rouler en développement
- ❌ Tests plus lents, ou moteur de test différent de celui de développement

### Option C — PostgreSQL

- ✅ Le plus rigoureux (types, contraintes, transactions)
- ❌ Mêmes inconvénients que l'option B pour le développement

## Décision

Nous retenons **SQLite** pour le développement et les tests, avec un **schéma portable** : rien
dans le code ne doit empêcher de passer à MySQL ou PostgreSQL en production.

### Conventions de schéma

**Migrations**

- Créées avec `php artisan make:migration`. Chaque changement est une nouvelle migration
  (`add_<colonne>_to_<table>_table`), avec une méthode `down()` qui l'annule.
- Une migration déjà partagée (poussée sur `main`) ne se modifie plus.
- Une migration qui ajoute une colonne calculée remplit aussi les lignes existantes (ex. :
  `search_name` des clients).

**Montants et taux**

| Donnée | Type | Exemple |
|---|---|---|
| Totaux, soldes, montants de facture | `decimal(12, 2)` | `customer_orders.total` |
| Prix unitaires, frais | `decimal(10, 2)` ou `decimal(8, 2)` | `cancellation_fee` |
| Pourcentages | `decimal(5, 2)` | `cancellation_fee_percent` |
| Multiplicateurs, frais fournisseur | `decimal(8, 4)` | `suppliers.base_multiplier` |
| Taux de change | `decimal(12, 6)` | `currencies.rate` |

Dans les modèles, ces colonnes sont castées en `float`, et chaque calcul monétaire se termine par
`round(…, 2)`.

**Statuts**

- Une colonne `string` et un enum PHP « backed » dans `app/Enums/`, casté dans le modèle
  (`'status' => CustomerOrderLineStatus::class`).
- Pas de `$table->enum()` pour un nouveau statut : ajouter une valeur ne doit pas exiger de modifier
  le schéma. Les trois colonnes `type` qui utilisent `enum()` sont antérieures à cette décision.

**Clés étrangères**

Toujours `foreignId()->constrained()`. La règle de suppression dépend du sens de la relation :

| Règle | Quand | Exemples |
|---|---|---|
| `restrictOnDelete()` | L'enfant est un document de valeur : on ne supprime pas le parent tant qu'il existe | commandes client ← client, paiements ← commande, lignes de facture ← ligne de réception, mouvements d'inventaire ← produit |
| `nullOnDelete()` | Simple référence optionnelle : l'historique survit au parent | `customer_orders.store_id`, auteur d'un mouvement (`user_id`) |
| `cascadeOnDelete()` | L'enfant n'a aucun sens sans son parent | lignes d'une semaine type, UPC d'un produit, horaires d'un employé |

**Pas de suppression logique (`SoftDeletes`)**

On désactive au lieu de supprimer :

- `is_active` : usagers, magasins, fournisseurs, modes de paiement ;
- `archived_at` : listes de prix.

**Valeurs historiques figées**

Une valeur qui peut changer ailleurs (prix d'un produit, pourcentage d'un magasin) est copiée sur la
ligne au moment de la transaction (`unit_price`, `cancellation_fee`). Voir
[ADR 0004](0004-cancellation-fee.md).

**Journal d'inventaire en ajout seulement**

- `inventory_stocks` : quantités courantes par état, une ligne par produit.
- `inventory_movements` : chaque passage d'un état à un autre, avec sa source (`reference`
  polymorphe) et son auteur. Pas de colonne `updated_at` ; le modèle lance une `LogicException` à
  toute tentative de modification ou de suppression.
- `inventory_movements.product_id` est en `restrictOnDelete()`. Une cascade faite par la base
  contournerait la protection du modèle : un produit qui a un journal ne peut donc pas être supprimé.

**Recherche**

Les noms sont normalisés dans une colonne indexée `search_name` (minuscules, sans accents) plutôt
que de dépendre d'une collation propre à un moteur.

**Portabilité**

- Passer par Eloquent ou le query builder.
- Le SQL brut (`whereRaw`) est limité à des expressions standard (`LOWER()`, arithmétique).
  Aucune fonction propre à SQLite.

**Données de départ**

- Les seeders ne créent que les données de référence : permissions, rôles, devises.
- Chaque modèle a une factory, utilisée par les tests.

## Conséquences

**Positives**

- `php artisan migrate:fresh --seed` remet l'application à zéro en quelques secondes.
- La suite de tests tourne en mémoire, sans service externe.
- Les documents de valeur (commandes, paiements, factures, réceptions) ne peuvent pas disparaître
  par la suppression d'un parent.

**Négatives / limitations**

- **Précision des montants :** en SQLite, les décimaux sont stockés en virgule flottante. Le
  `round(…, 2)` systématique compense, mais les sommes faites par la base (`sum()`) peuvent
  présenter des écarts infimes.
- **Concurrence :** en SQLite, `lockForUpdate()` n'a aucun effet. Les transactions restent sûres
  parce que la base n'accepte qu'un écrivain à la fois, mais les verrous par ligne ne serviront qu'en
  passant à MySQL ou PostgreSQL.
- **Références polymorphes :** sans `morphMap`, `reference_type` stocke le nom complet de la classe
  (`App\Models\Reception`). Renommer un modèle briserait les mouvements existants.

**Protections mises en place**

- Contraintes de clés étrangères actives, avec des règles de suppression choisies une par une.
- Journal d'inventaire non modifiable : au niveau du modèle (`LogicException`) et au niveau de la
  base (`restrictOnDelete()` sur le produit).
- Désactivation plutôt que suppression pour les entités référencées par l'historique.

## Questions ouvertes

- **Moteur de production :** SQLite (fichier unique, sauvegarde simple) ou MySQL / PostgreSQL
  (plusieurs postes de caisse qui écrivent en même temps) ? À décider avant le déploiement, dans un
  ADR distinct.
- **`Relation::enforceMorphMap()`** pour découpler le journal des noms de classes.
- **Montants en cents (`integer`)** plutôt qu'en décimal, pour une précision exacte quel que soit
  le moteur ?
- **Regrouper les migrations** (`php artisan schema:dump --prune`) avant la mise en production.

## Références

- `.env` / `.env.example` (`DB_CONNECTION=sqlite`), `phpunit.xml` (`DB_DATABASE=:memory:`)
- `database/migrations/`, `database/seeders/`, `database/factories/`
- `app/Models/InventoryMovement.php`
