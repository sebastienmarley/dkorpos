# ADR 0009 — Étiquettes de prix des démonstrateurs

- **Statut :** Accepté
- **Date :** 2026-10-17
- **Issue liée :** #36

## Contexte

Quand le prix de vente d'un produit en démonstration change, rien ne rappelle de changer son étiquette.
Les achats doivent aviser le plancher fournisseur par fournisseur, et une étiquette oubliée affiche un
mauvais prix au client.

Plusieurs contraintes encadrent la solution.

1. **Le prix de vente n'est pas enregistré.** Il est calculé à la lecture : coût × multiplicateur du
   fournisseur, puis tranches de prix ([ADR 0008](0008-money-in-cents.md)). Il change par trois chemins :
   - le coût du produit (fiche produit, application d'une liste de prix) ;
   - le fournisseur du produit ;
   - le multiplicateur du fournisseur (ses composantes, ou le taux de sa devise).
2. **Le stock en démo est un total pour l'entreprise** (`inventory_stocks.quantity_in_demo`), sans
   répartition par magasin.
3. **Le support est fixe** : une étiquette 4 × 6 po en portrait, dont seule une zone de 3,5 × 3,5 po en
   haut est imprimable (le bas est préimprimé). Elle passe par l'alimentation manuelle d'une imprimante
   laser monochrome ordinaire, sans imprimante d'étiquettes ni pilote particulier.
4. **L'application n'avait rien pour imprimer** : pas de vue imprimable, de PDF, de code-barres ni de
   notifications.

Le chantier a été découpé en quatre étapes : le gabarit, la file d'impression, la détection des
changements de prix, puis la notification.

## Options considérées

### Détection des changements de prix

#### Option A — Écouter chaque chemin de changement de prix

Chaque endroit qui modifie un coût ou un multiplicateur crée lui-même la demande d'étiquette.

- ✅ Réaction immédiate
- ❌ Un chemin oublié (nouvel import, modification du taux d'une devise) passe en silence
- ❌ Un prix qui revient à sa valeur d'origine laisse une demande inutile dans la file

#### Option B — Historique des prix de vente

Chaque calcul de prix est enregistré, et on compare les deux derniers.

- ✅ Permet de répondre à « quel était le prix le 3 mars ? »
- ❌ Il faudrait enregistrer un prix calculé partout où il change, avec le même risque d'oubli qu'en A
- ❌ Ne dit pas ce qui est réellement imprimé sur le démonstrateur

#### Option C — Retenir le prix affiché sur l'étiquette ✔️

On enregistre sur le produit le prix réellement imprimé (`products.displayed_price`). Un démonstrateur a
besoin d'une étiquette quand ce prix diffère du prix de vente actuel.

- ✅ **Indépendant du chemin** : peu importe pourquoi le prix a changé, la comparaison le voit
- ✅ Un prix revenu à sa valeur d'origine n'a plus besoin d'étiquette
- ✅ Représente ce qui est réellement sur le plancher
- ❌ Il faut décider à quel moment le prix est « affiché » : à la confirmation de l'impression

### Impression

#### Option A — PDF généré par le serveur (`dompdf`, `snappy`)

- ✅ Rendu identique d'un navigateur à l'autre
- ❌ Nouvelle dépendance, et pour `snappy` un binaire sur le serveur
- ❌ Le rendu CSS de `dompdf` est limité (pas de grille)

#### Option B — Page HTML, CSS d'impression et boîte d'impression du navigateur ✔️

- ✅ Aucune dépendance; le gabarit est une vue Blade ordinaire
- ✅ `@page { size: 4in 6in }` décrit exactement le support
- ❌ Les marges et l'échelle dépendent des réglages de la boîte d'impression (rappelés à l'écran)

### Code-barres

#### Option A — Paquet de code-barres

- ✅ Nombreuses symbologies
- ❌ Nouvelle dépendance pour une seule symbologie

#### Option B — Code 128 maison en SVG ✔️

- ✅ Une centaine de lignes, sans dépendance; le SVG reste net à toute résolution
- ✅ Le jeu C encode deux chiffres par symbole : un identifiant de produit reste compact
- ❌ À maintenir nous-mêmes (table des motifs vérifiée par les tests)

### Notification

| Option | Avantage | Inconvénient |
|---|---|---|
| Badge du menu seulement | Rien à construire | Ne se remarque pas, pas d'historique |
| Carte sur l'accueil | Simple | Visible seulement sur l'accueil |
| Courriel quotidien | Rejoint l'usager hors de l'application | Demande un envoi de courriels en production |
| **Cloche de notifications en base** ✔️ | Visible sur toutes les pages, réutilisable pour d'autres alertes | Table et composant à ajouter |

## Décision

Nous retenons le **prix affiché** pour la détection, l'**impression par le navigateur**, un **Code 128
maison** et une **cloche de notifications**.

### Gabarit

- `resources/views/components/labels/product.blade.php` : une étiquette. La zone utile est divisée en
  trois tiers :
  - en haut, le prix de vente en gros caractères, sans les cents quand le montant est rond, et dont la
    taille diminue selon la longueur du prix ;
  - au milieu, le fournisseur, la collection et la couleur, alignés à gauche ;
  - en bas, le modèle aligné à droite, puis l'identifiant à gauche et son code-barres à droite.
- `resources/views/labels/print.blade.php` : une page par étiquette. La position de la zone utile est
  réglable par deux variables CSS (`--label-area-top`, `--label-area-left`, 0,25 po par défaut). À
  l'écran, un aperçu montre la feuille, la zone utile et la partie préimprimée.
- `App\Support\Code128` : jeu C pour un nombre pair de chiffres, jeu B sinon, avec zones de silence de
  10 modules.

### File d'impression

| Élément | Rôle |
|---|---|
| Table `label_requests` | Produit, quantité, raison, demandeur, `printed_at`, `printed_by`. Les demandes imprimées restent pour l'historique |
| Enum `LabelReason` | `Manual` (demande manuelle), `PriceChange` (changement de prix), `NewDemo` (démonstrateur jamais étiqueté) |
| `LabelRequest::enqueue()` | Ajoute à la file; une demande en attente pour le même produit et la même raison est **cumulée** |
| `LabelRequest::markPrinted()` | Marque les demandes imprimées et **retient le prix imprimé** sur chaque produit |
| `<x-label-button :product>` | Bouton réutilisable, à placer partout. Il ouvre la modale globale `LabelQueueForm` (déclarée une fois dans le layout), qui propose « Ajouter à la file » ou « Imprimer maintenant » |
| Page `labels.index` | La file : sélection, quantité, retrait, puis **Imprimer** |

L'impression charge la page des étiquettes dans un cadre hors écran et appelle `print()`. À la fermeture
de la boîte d'impression, une modale demande si tout s'est bien imprimé. Seule la confirmation marque
les demandes imprimées : une impression ratée ou annulée laisse la file intacte.

### Détection

`App\Actions\QueuePriceChangeLabels` compare, pour chaque produit en démo, `displayed_price` au prix de
vente actuel :

| Situation | Résultat |
|---|---|
| Pas en démo | Aucune demande; une demande automatique en attente est retirée |
| En démo, jamais étiqueté (`displayed_price` vide) | Demande `NewDemo` |
| En démo, prix différent du prix affiché | Demande `PriceChange` |
| En démo, prix égal au prix affiché | Aucune demande; une demande automatique en attente est retirée |

La quantité d'une demande automatique est le nombre d'unités en démo. Les demandes manuelles ne sont
jamais touchées.

L'action est déclenchée :

- quand un produit est enregistré et que son coût, son fournisseur ou son prix affiché a changé
  (`Product::saved`) ;
- quand le multiplicateur d'un fournisseur change (`Supplier::saved`), y compris par le taux de sa devise ;
- quand le nombre d'unités en démo change (`InventoryStock::saved`) ;
- chaque jour à 05 h 30, après l'application des listes de prix (commande
  `labels:queue-price-changes`), pour rattraper une écriture qui contourne les événements des modèles.

### Notification

- Table `notifications` de Laravel (canal `database`) et composant `NotificationsBell`, dans l'en-tête
  du menu et dans l'en-tête mobile. Le panneau est téléporté dans `<body>` (`x-teleport`) et ancré au
  bouton (`x-anchor`), pour ne pas être coupé par la barre latérale.
- Toute notification fournit `title`, `body`, `url` et `icon` : la cloche n'est pas propre aux étiquettes.
- `App\Notifications\LabelsToPrint` est envoyée par la passe quotidienne aux usagers **actifs** qui ont
  `labels.print`, seulement si des étiquettes attendent. Le rappel non lu précédent est remplacé.
  Quand la file se vide, les rappels sont marqués lus.
- Le badge « Étiquettes » du menu donne en plus le nombre en attente en temps réel.

### Permissions

| Permission | Rôles par défaut |
|---|---|
| `labels.create` (ajouter à la file) | Tous les rôles qui ont accès aux pages |
| `labels.view` (voir la file) | Direction, administrateur, propriétaire |
| `labels.print` (imprimer et confirmer) | Direction, administrateur, propriétaire |

L'étiquette d'un seul produit (`products.label`) demande `products.view`.

## Conséquences

**Positives**

- Tout changement de prix d'un démonstrateur aboutit à une étiquette, quel que soit son chemin.
- Aucune dépendance ajoutée.
- Le bouton, la file et la cloche servent à d'autres besoins : étiquette demandée par un vendeur,
  autres alertes.

**Négatives / limitations**

- **« Imprimer maintenant » ne met pas à jour le prix affiché** : cette impression n'a pas de
  confirmation. Pour qu'un démonstrateur soit considéré à jour, il faut passer par la file.
- La quantité d'une demande automatique suit le stock en démo : une modification à la main dans la file
  est écrasée au prochain changement de prix ou de stock du produit.
- Le stock en démo n'est pas réparti par magasin : la file ne dit pas où poser les étiquettes.
- L'ordre de la boîte d'impression et de la confirmation dépend du navigateur. Dans Safari, la modale
  peut apparaître avant la fermeture de la boîte.
- `products.displayed_price` est le 38e montant en cents (`MoneySchemaTest`).
- Les événements des modèles ajoutent une requête à chaque enregistrement de produit dont le coût change,
  notamment à l'application d'une liste de prix.

**Protections mises en place**

- Au déploiement, la migration copie le prix de vente actuel dans le prix affiché des démonstrateurs
  existants : on présume leurs étiquettes justes, et tout le plancher n'est pas mis en file d'un coup.
- La passe quotidienne rattrape les écritures qui contournent les événements des modèles
  (`saveQuietly()`, mises à jour par requête).
- Une demande imprimée n'est jamais modifiée : `enqueue()` en crée une nouvelle.
- La table des motifs du Code 128 est vérifiée (107 motifs distincts, 11 modules chacun, 13 pour
  l'arrêt), ainsi que les sommes de contrôle des jeux B et C.

## Questions ouvertes

- **Promotions** (#25) : un rabais sur un département ou une catégorie devra générer des étiquettes, puis
  remettre le prix à la fin de la promotion. La raison `LabelReason` pourra s'étendre.
- **Répartition du stock en démo par magasin** : permettrait une file par magasin.
- **Confirmer « Imprimer maintenant »** pour qu'elle mette aussi à jour le prix affiché.
- **Réglage de la zone utile** par magasin ou par imprimante, si les feuilles ou les imprimantes
  diffèrent.

## Références

- Migrations : `create_label_requests_table`, `add_displayed_price_to_products_table`,
  `create_notifications_table`
- Code : `App\Actions\QueuePriceChangeLabels`, `App\Actions\NotifyLabelsToPrint`,
  `App\Models\LabelRequest`, `App\Support\Code128`, `App\Livewire\Labels\Index`,
  `App\Livewire\LabelQueueForm`, `App\Livewire\NotificationsBell`
- Tests : `ProductLabelTest`, `LabelQueueTest`, `PriceChangeLabelsTest`, `LabelsNotificationTest`,
  `Unit/Support/Code128Test`
- [ADR 0008](0008-money-in-cents.md) : calcul et tranches du prix de vente
