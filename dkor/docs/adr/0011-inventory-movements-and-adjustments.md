# ADR 0011 — Mouvements d'inventaire, unités FIFO et ajustements

- **Statut :** Proposé
- **Date :** 2026-10-18
- **Issue liée :** #52 (englobe #56 et #57)

## Contexte

Le propriétaire veut un inventaire exact (#52). Il doit pouvoir déclarer une perte (bris, vol, produit
jeté), sortir un produit pour l'usage du magasin et corriger l'inventaire à la prise d'inventaire annuelle
pour clôturer l'année fiscale. Chacune de ces opérations produit un mouvement d'inventaire **et une écriture
comptable**. Elles sont réservées au plus haut niveau.

La plupart des concepts existent déjà :

1. **Les quantités par état.** `inventory_stocks` a une ligne par produit et une colonne par état exclusif
   (`InventoryStatus::column()`). Seul « En stock » est disponible à la vente
   (`InventoryStock::quantityAvailable()`).
2. **Le journal.** `InventoryMovement::record()` est le seul point d'écriture des quantités. Il verrouille la
   ligne de stock (`lockForUpdate`), refuse une quantité insuffisante, met à jour les colonnes et consigne le
   mouvement dans la même transaction. Le journal ne se modifie ni ne se supprime.
3. **La réception (#57).** Elle fait passer la quantité reçue de « En commande » à « En stock »
   (`SupplierOrder::applyReceiptLines()`). La partie endommagée va à « Défectueux (stock) » et la partie
   manquante sort de « En commande ».
4. **Les unités (#56).** Chaque unité reçue en bon état crée une `inventory_units` au coût de la commande,
   datée du jour (`inserted_at`). Le ramassage marque les plus anciennes comme livrées (`scopeFifo()`), le
   retour client les remet en inventaire, et le renversement de réception les supprime.

Une révision du code (2026-10-18) montre ce qui manque, et ce qui ne tient pas :

- **Aucune sortie d'inventaire hors document.** La page Journal (`App\Livewire\Inventory\Movements`) ne
  fait que des transferts entre deux états. Il est impossible d'enregistrer une perte, l'usage magasin ou le
  stock initial d'un produit.
- **Les transferts manuels désynchronisent les documents.** `isManuallyMovable()` n'exclut que « En
  commande ». On peut donc déplacer du « Réservé client » sans toucher aux lignes de commande client ; le
  prochain ramassage échoue alors faute de stock réservé.
- **Les unités ne suivent pas les quantités.** Un transfert vers « Perdu » ou « Défectueux » ne touche pas
  aux unités. Une réception endommagée n'en crée pas, et un retour défectueux non plus. Aucun invariant ne
  relie le nombre d'unités aux quantités.
- **Un document peut s'appliquer deux fois.** `Reception::complete()` et `SupplierOrder::send()` vérifient
  le statut sur l'instance chargée, avant la transaction. Une instance périmée (deux onglets, double clic)
  réapplique le document : une réception de 4 donne 8 unités, et un envoi de 10 met 20 « en commande ».
- **Aucune écriture comptable.** L'application n'a pas de grand livre.

Il faut décider maintenant, avant de construire l'ajustement : il est le premier processus qui sort de la
valeur d'inventaire sans document d'achat ni de vente.

## Options considérées

### Unités d'inventaire

#### Option A — Unités pour le coût de réception seulement (statu quo)

- ✅ Rien à changer
- ❌ Aucun invariant entre unités et quantités : la valeur d'une perte ne peut pas être calculée
- ❌ Le ramassage FIFO peut consommer une unité perdue, et une unité perdue reste « en inventaire »

#### Option B — Une colonne d'état sur chaque unité

- ✅ Traçabilité unité par unité (où est l'unité reçue tel jour)
- ❌ Chaque mouvement doit choisir quelles unités changent d'état : beaucoup de code, et deux sources de
  vérité (colonnes de quantité et états des unités)
- ❌ Ne sert à rien tant que les produits n'ont pas de numéro de série

#### Option C — Couches de coût FIFO ✔️

Une unité ouverte (`delivered_at` vide) est une **couche de coût** : une unité possédée par le magasin, à son
coût d'achat. Elle n'a pas d'état. Le flux des coûts suit le premier entré, premier sorti, indépendamment de
l'endroit physique de l'unité.

- ✅ Un invariant simple : unités ouvertes = somme des états valorisés
- ✅ La valeur d'une sortie (perte, vente) est la somme des couches consommées : c'est ce que demande
  l'écriture comptable
- ❌ Ne dit pas quelle unité physique est en démo ou perdue (pas requis)

### Écritures comptables

#### Option A — Valeur seulement, sur le mouvement

- ✅ Peu de code
- ❌ Le comptable doit refaire les écritures à partir d'un rapport

#### Option B — Grand livre avec table de comptes ✔️

- ✅ L'écriture est équilibrée, datée, liée à l'ajustement, et prête à exporter
- ✅ Les comptes sont des données : le comptable ajuste les codes à son plan comptable
- ❌ Nouveau domaine (comptes, écritures), qui n'est alimenté au départ que par les ajustements

#### Option C — Comptes dans un fichier de configuration

- ✅ Pas de table de comptes
- ❌ Changer un code de compte demande un déploiement

## Décision

Nous retenons les **couches de coût FIFO** (option C) et un **grand livre avec table de comptes**
(option B). L'ajustement devient un document, avec sa permission.

### Les états

Les neuf états restent exclusifs : une unité est dans un seul état à la fois.

| État | Qui le fait bouger | Valorisé | Transfert manuel |
|---|---|---|---|
| En stock | Réception, réservation, retour, ajustement | Oui | Oui |
| En démo | Transfert manuel | Oui | Oui |
| Défectueux (stock) | Réception endommagée, retour défectueux, transfert | Oui | Oui |
| Perdu | Transfert manuel (« perdu / retrouvé ») | Oui | Oui |
| Réservé client | Commande client (réservation, ramassage, réception liée) | Oui | Non |
| En livraison | Livraison (#12) | Oui | Non |
| Défectueux (expédié) | Dossier défectueux retourné au fournisseur (à venir) | Oui | Non |
| En commande | Commande fournisseur | Non | Non |
| En commande client | Aucun processus : non utilisé | Non | Non |

- **Valorisé** : la marchandise appartient au magasin et a un coût. « Perdu » est un état d'attente : il
  reste valorisé, sans écriture, jusqu'à ce qu'on le retrouve (transfert) ou qu'on le sorte (ajustement).
  « Défectueux » aussi : la marchandise est payée tant que le fournisseur ne l'a pas créditée.
- **Transfert manuel** (`inventory.move`) : seulement entre les quatre **états physiques**. Les autres
  suivent leur document. Un transfert ne change pas la valeur de l'inventaire : il ne passe aucune écriture.
- « En commande client » est conservé, mais non utilisé : la marchandise commandée pour un client passe par
  « En commande » puis « Réservé client ». Son sort est une question ouverte.

### Le journal

- `InventoryMovement::record()` reste **le seul écrivain** de `inventory_stocks` et du cycle de vie des
  unités. Aucun autre code n'écrit ces tables, ni par requête (`update`, `increment`, `DB::table`), ni sans
  événements (`saveQuietly`, `withoutEvents`). Le déclencheur des étiquettes de démo (`InventoryStock::saved`,
  [ADR 0009](0009-price-labels.md)) dépend de cette règle.
- Un état vide signifie l'extérieur : `null → état` est une entrée, `état → null` une sortie.
- Chaque mouvement a un **type** et une **référence** (document source, polymorphe). Les ajustements
  ajoutent le type `Adjustment`, qui référence l'`InventoryAdjustment`.

### Les unités

Le passage entre un état valorisé et un état non valorisé (ou l'extérieur) change les unités. `record()`
s'en charge, pour que la règle soit écrite une seule fois.

| Mouvement | Effet sur les unités | Coût |
|---|---|---|
| Non valorisé → valorisé (réception, endommagée comprise) | Crée une unité par quantité | Coût de la ligne de commande |
| Valorisé → En commande (renversement de réception) | Supprime les unités ouvertes de la ligne de réception | — |
| Valorisé → extérieur (ramassage, livraison, ajustement de sortie) | Ferme les plus anciennes (FIFO), avec la date et le mouvement de sortie | Somme des couches fermées |
| Extérieur → valorisé (retour client, retour défectueux) | Rouvre les unités fermées par la sortie de cette commande, sinon en crée | Coût d'origine, sinon coût du produit |
| Extérieur → valorisé (ajustement d'entrée) | Crée les unités | Coût du produit (`products.cost`) |
| Valorisé → valorisé (transfert, réservation, départ en livraison) | Aucun | — |

- La table `inventory_units` reçoit le mouvement qui a fermé l'unité (`exit_movement_id`). Le retour client
  rouvre ainsi les unités de **sa** commande, au lieu des dernières unités livrées de n'importe quelle
  commande.
- Chaque mouvement qui crée ou ferme des unités consigne sa **valeur** (`inventory_movements.value`, en
  cents, [ADR 0008](0008-money-in-cents.md)).
- La réception endommagée crée maintenant des unités, en « Défectueux (stock) ». Le crédit du fournisseur
  les fermera (dossier défectueux, à venir).
- Une sortie de stock entré avant le suivi des unités (aucune unité en main) est valorisée au coût du produit
  pour la partie sans unité, au lieu d'échouer.
- Réalisé dans `InventoryMovement::record()` (paramètre `unitCost` pour la réception). La réception, le
  renversement, le ramassage et les retours n'écrivent plus les unités eux-mêmes.

### Les ajustements

Un ajustement corrige l'inventaire sans document d'achat ni de vente. Table `inventory_adjustments` :
produit, motif, sens (entrée ou sortie), état touché, quantité, note obligatoire et usager. Son mouvement et
son écriture le référencent (référence polymorphe) : la valeur se lit sur le mouvement, sans être recopiée. Un
ajustement ne se modifie ni ne se supprime : une erreur se corrige par l'ajustement inverse.

| Motif (`InventoryAdjustmentReason`) | Sens | États permis | Compte débité / crédité |
|---|---|---|---|
| Bris ou produit jeté | Sortie | En stock, En démo, Défectueux (stock), Perdu | Pertes d'inventaire / Stock |
| Vol | Sortie | En stock, En démo, Perdu | Pertes d'inventaire / Stock |
| Perte définitive | Sortie | Perdu | Pertes d'inventaire / Stock |
| Usage magasin | Sortie | En stock, En démo | Fournitures du magasin / Stock |
| Correction de comptage | Entrée ou sortie | États physiques | Écart d'inventaire / Stock (ou l'inverse) |
| Stock initial | Entrée | En stock, En démo | Stock / Solde d'ouverture |

- Le service `App\Actions\AdjustInventory` fait l'ajustement dans une transaction : il vérifie la permission
  et le motif, enregistre le mouvement (`record()`, qui ferme ou crée les unités et en donne la valeur), puis
  passe l'écriture du même montant. Une valeur nulle (unités sans coût) ne passe pas d'écriture.
- Le composant `App\Livewire\Inventory\Adjustments` (« InventoryAdjustment » dans l'issue) offre le
  formulaire et la liste des ajustements (page Ajustements d'inventaire, `inventory/adjustments`). Il est
  protégé par `inventory.adjust`.
- Les erreurs de réception, les écarts à la réception et le perdu/retrouvé restent hors périmètre (#52) :
  la facturation, les réceptions et les transferts s'en chargent déjà.

### Le grand livre

- `ledger_accounts` : code (unique), nom, type (`LedgerAccountType` : actif, passif, capitaux propres,
  produit, charge) et une clé système (`system_key`) pour les comptes dont l'application a besoin.
  `ReferenceDataSeeder` crée les comptes système ([ADR 0007](0007-reference-data-seeders.md)) : Stock (1300),
  Solde d'ouverture (3900), Pertes d'inventaire (5110), Écart d'inventaire (5120) et Fournitures du magasin
  (5400). Le comptable peut changer leur code et leur nom dans la page Grand livre (`accounting/ledger`), pas
  leur clé.
- `ledger_entries` : date, description, référence polymorphe (l'ajustement), usager.
  `ledger_entry_lines` : compte, débit, crédit (cents). Une écriture est **équilibrée** et ne se modifie ni ne
  se supprime : une correction passe une écriture inverse.
- `LedgerEntry::post()` est le seul point de création. Il refuse une écriture déséquilibrée, vide, ou une
  ligne qui a à la fois un débit et un crédit.
- Les factures de transporteur (issue #95, [ADR 0010](0010-customer-order-deliveries.md)) passent aussi une
  écriture : Frais d'expédition et transport (5200) au débit pour le montant avant taxes, un compte « à
  recevoir » par taxe (`LedgerAccount::forTaxReceivable()`, codes 1410, 1411…) au débit, et Comptes fournisseurs
  (2100) au crédit pour le total.
- Au départ, seuls les ajustements passent des écritures. Les réceptions, les ventes (coût des marchandises
  vendues) et les crédits fournisseurs suivront : voir les questions ouvertes.

### La prise d'inventaire annuelle (#88)

- Une **prise d'inventaire** (`InventoryCount`) a une date de comptage, qui date ses écritures. Une seule
  prise est en cours à la fois.
- **Quatre types de compte**, un par état physique : Stock (`InStock`), Stock client (`ReservedCustomer`),
  Défectueux (`DefectiveStock`) et Démonstrateur (`InDemo`). Une ligne par produit et par type
  (`inventory_count_lines`), créée au démarrage pour tout ce qui a une quantité, ou ajoutée pour un produit
  trouvé.
- **« Perdu » est vide pendant la prise.** Au démarrage, puis au gel du stock, les perdus non retrouvés
  reviennent « En stock » (type de mouvement `CountLostReturned`, référencé à la prise). Ce qui manque encore
  au comptage devient une perte à la finalisation.
- **Gel hybride.** Démo, défectueux et stock client se comptent pendant les heures d'ouverture, sans gel : leur
  attendu est la quantité au moment de la finalisation, et l'équipe des ventes note les variances sur les lignes.
  Le stock se compte après la fermeture : « Geler le stock » (`stock_frozen_at`) fait refuser par `record()` tout
  mouvement qui touche « En stock » ou « Perdu », jusqu'à la finalisation ou l'abandon. Une ligne de stock ne se
  compte qu'une fois le stock gelé.
- **La finalisation** (`App\Actions\AnnualInventoryFinalUpdate`, permission `inventory.adjust`), dans une seule
  transaction : elle fige l'attendu de chaque ligne et crée un ajustement par écart, daté du jour du comptage.
  - Un **manque** est une perte : motif `AnnualCountLoss` (« Perte à l'inventaire annuel », Pertes
    d'inventaire / Stock, au coût FIFO), réservé à la finalisation.
  - Un **surplus** est une correction de comptage (`CountCorrection`, Stock / Écart d'inventaire, au coût du
    produit).
  - Elle est refusée sans gel, avec une ligne ni comptée ni conservée, ou avec un écart dans le stock client :
    ce stock appartient à une commande, qu'il faut corriger d'abord (I4).
- Une ligne « conservée sans comptage » garde son attendu, sans ajustement. Une prise finalisée ou abandonnée
  ne se modifie plus; l'abandon ne passe aucun ajustement et laisse en stock les perdus remis.
- **Rapport** : la page de la prise (totaux par type de compte, pertes et corrections) et un export CSV.

#### Comptes doubles, zones et douchette

- **Lecteur** : une douchette (USB ou Bluetooth), qui tape le code et Entrée comme un clavier, sur la page
  mobile « Mes comptes ». Aucune dépendance ajoutée; la lecture par caméra reste hors périmètre. Le code est
  cherché dans `product_upcs`; un code inconnu est consigné sans produit, et un superviseur (ou qui modifie les
  produits) l'associe à un produit, comme dans la commande client.
- **Feuilles de compte** (`InventoryCountSheet`) : une portée (Défectueux, Démonstrateur et Stock client en
  entier; le Stock par **zone permanente**, `inventory_zones`), une passe A, B ou recompte, et un compteur
  attribué par le superviseur. Deux passes d'une même portée ne vont jamais à la même personne. Une feuille du
  stock attend le gel. Chaque lecture ajoute 1, ou la quantité saisie pour une boîte; la dernière lecture
  s'annule, une quantité se corrige, et tout est consigné (`inventory_count_scans`).
- **Réconciliation** : un produit dont A et B concordent est retenu. Les produits en désaccord sont recomptés
  par un troisième compteur, sur une feuille limitée à ces produits. Le recompte est retenu s'il concorde avec
  A ou B; sinon, seulement si le superviseur le retient. Les quantités retenues (`inventory_count_results`)
  alimentent les lignes de la prise (la somme des zones pour le stock) une fois toutes les portées de l'état
  réconciliées; ces lignes ne se saisissent plus à la main (« Conservée » reste possible).
- Sans feuilles, une prise se compte à la main comme avant. Avec des feuilles, la finalisation est refusée tant
  qu'une portée n'est pas réconciliée, et l'export CSV donne le détail par zone.

### Les permissions

- `inventory.adjust` (« Ajuster — inventaire ») : nouvelle permission, déclarée dans `config/access.php`, dans
  aucun accès par défaut. Seul le rôle qui a toutes les permissions (propriétaire) l'a au départ.
- `inventory.move` : inchangée, mais limitée aux transferts entre états physiques.
- `inventory.count` : voir les prises d'inventaire et saisir les comptes, dans les accès gestion (directeurs)
  et entrepôt. Démarrer, geler, abandonner et finaliser demandent `inventory.adjust`.
- `inventory.supervise` : gérer les zones, préparer et attribuer les feuilles, créer les recomptes, réconcilier
  et associer les codes inconnus, dans l'accès gestion (directeurs).
- `ledger.view` (consulter le grand livre) et `ledger.edit` (code et nom des comptes) : dans l'accès
  comptabilité.

### Les invariants

Pour chaque produit, en tout temps :

| # | Invariant |
|---|---|
| I1 | **Journal = quantités** : pour chaque état, les entrées moins les sorties du journal égalent la colonne. |
| I2 | **Aucune quantité négative.** |
| I3 | **Unités = quantités** : les unités ouvertes égalent la somme des états valorisés. |
| I4 | **Réservé = commandes clients** : « Réservé client » + « En livraison » égalent la somme des `quantity_reserved` des lignes de produit des commandes clients. |
| I5 | **En commande = commandes fournisseurs** : « En commande » égale ce qui reste à recevoir des lignes de produit des commandes envoyées ou partiellement reçues. |
| I6 | « En commande client » vaut 0. |
| I7 | **Valeur = écriture** : la valeur d'un ajustement égale celle de son mouvement, qui égale la somme des couches créées ou fermées, et le montant de son écriture. |
| I8 | Toute écriture est équilibrée (débits = crédits). |
| I9 | Le journal, les ajustements et les écritures sont immuables. |
| I10 | **Un document s'applique une fois** : son statut est relu, et sa ligne verrouillée, dans la transaction qui l'applique. |

## Conséquences

**Positives**

- La valeur de l'inventaire et celle de chaque perte se calculent à partir des unités, au coût réel FIFO.
- Une seule règle pour les unités, dans `record()`, au lieu d'un traitement par processus (réception,
  ramassage, retour).
- Les transferts manuels ne peuvent plus désynchroniser une commande.
- Les invariants sont vérifiables par un test, et plus tard par une commande de contrôle.

**Négatives / limitations**

- Le grand livre ne reçoit au départ que les ajustements : le solde du compte Stock n'égale donc pas encore la
  valeur de l'inventaire.
- Le coût d'une unité peut changer après coup, à la facture fournisseur (`SupplierInvoice`, qui met à jour le
  coût des unités de la réception). Une écriture déjà passée sur ces unités ne suit pas.
- Les produits en stock avant la mise en service n'ont pas d'unités : il faut les entrer par un ajustement
  « Stock initial » pour respecter I3.
- Le flux FIFO est un flux de coûts : il ne dit pas quelle unité physique est en démo ou perdue.
- Le test « gère les neuf états » (`InventoryMovementsTest`) transfère entre tous les états : il devra
  utiliser les documents pour les états non physiques.

**Protections mises en place**

- `record()` refuse une quantité nulle ou négative, des états identiques et une quantité insuffisante ; il
  verrouille la ligne de stock. Il refuse aussi un mouvement de type `Transfer` qui touche un état non physique
  ou l'extérieur, même hors de la page Journal.
- `InventoryStock` refuse d'enregistrer une quantité négative (I2), même par un chemin qui contourne le journal.
- Les documents relisent leur statut sous verrou dans leur transaction (I10), avec `refreshForUpdate()`
  d'Eloquent : réception (`complete`, `discard`, lignes, articles en trop) et commande fournisseur (envoi,
  annulation, lignes, substitution, annulation de ligne, renversement). Les commandes clients le font déjà
  (`lockForUpdate()->findOrFail()`).
- Sur SQLite, `lockForUpdate` est sans effet. Deux transactions qui écrivent en même temps s'excluent :
  l'une échoue (base occupée) au lieu d'écraser l'autre. La relecture du statut dans la transaction règle le
  cas de l'instance périmée, que le verrou ne couvre pas.
- `expectInventoryIntegrity()` (`tests/Pest.php`) vérifie I1 à I6 pour un produit dont tout le stock est
  entré par le journal. `InventoryUnitsTest` couvre le cycle de vie des unités.
- `inventory.adjust` est vérifiée dans le composant et dans l'action ; la note est obligatoire.

## Questions ouvertes

- **Les autres écritures** : réception (Stock / Fournisseurs), vente (Coût des marchandises vendues / Stock),
  crédit fournisseur. Elles rendront le compte Stock égal à la valeur de l'inventaire.
- **Le dossier défectueux** : retour au fournisseur (« Défectueux (expédié) »), crédit ou remplacement, et
  fermeture des unités.
- **« En commande client »** : retirer l'état, ou l'utiliser pour les commandes spéciales.
- **La livraison (#12)** : remise au client (« En livraison » → extérieur, unités fermées en FIFO), départ
  (« Réservé client » → « En livraison ») et retour au magasin. La session des livraisons s'en charge, en
  respectant I3 et I4.
- **Le mode de transaction SQLite** : `IMMEDIATE` sérialiserait les écritures dès le début de la transaction
  ([ADR 0006](0006-database.md)).

## Références

- Issues #52, #56, #57 ; #12 et #86 (livraisons)
- `app/Models/InventoryStock.php`, `app/Models/InventoryMovement.php`, `app/Models/InventoryUnit.php`
- `app/Enums/InventoryStatus.php`, `app/Enums/InventoryMovementType.php`
- `app/Livewire/Inventory/Movements.php`
- `app/Models/SupplierOrder.php` (`applyReceiptLines`, `reverseReceipt`), `app/Models/Reception.php`,
  `app/Models/CustomerOrder.php` (réservations, `handOver`, retours), `app/Models/DefectiveProduct.php`
- `tests/Feature/InventoryMovementsTest.php`, `tests/Feature/ReceptionsTest.php`
- [docs/data-model.md](../data-model.md), section Inventaire
- [ADR 0001](0001-permissions.md) : permissions ; [ADR 0006](0006-database.md) : SQLite ;
  [ADR 0007](0007-reference-data-seeders.md) : données de référence ; [ADR 0008](0008-money-in-cents.md) :
  montants en cents ; [ADR 0009](0009-price-labels.md) : étiquettes des démonstrateurs ;
  [ADR 0010](0010-customer-order-deliveries.md) : livraisons
