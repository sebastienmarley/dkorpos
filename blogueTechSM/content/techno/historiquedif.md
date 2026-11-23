---
title: "Historique des difficultés"
date: 2026-11-22
draft: false
description: "Les bogues et les difficultés du projet, du premier au dernier commit."
categories:
  - "techno"
tags:
  - "bogue"
  - "projet"
  - "réflexion"
---
## Préambule
Ce billet recense, en ordre chronologique, les difficultés et les bogues rencontrés du 21 septembre au 22 novembre 2026. Il s'appuie sur l'historique git (407 commits), les Issues GitHub, mon journal, mes entrées de blogue et le billet [Difficultés rencontrées]({{< relref "techno/difficulte.md" >}}), qui raconte plusieurs de ces histoires plus en détail. Les liens mènent aux commits, aux Issues et aux PR du dépôt. Quand aucune Issue n'est reliée, c'est indiqué.
### Notes
J'ai fait ce recensement via Claude, qui reprend mes termes, mon journal, mes descriptions de commit, etc. Pour les Issue non relié, c'est ce qui m'est reproché, je comprends un peu mieux maintenant, je croyais qu'une branche liée suffirait, mais bon j'ai appris, enfin je crois. Pour le reste je ne peux pas jurer qu'il s'agit de l'intégralité des problèmes, assurément certains ont été noyé au travers du développement sans jamais avoir un commit pour les répertorier, mais je crois que cela illustre bien le flow du projet.

## Septembre 2026

### 1. Déployer Hugo sur GitHub Pages
- **Date :** 21 et 22 septembre 2026
- **Commits :** [`868fa65`](https://github.com/sebastienmarley/dkorpos/commit/868fa65) à [`2f4c8a8`](https://github.com/sebastienmarley/dkorpos/commit/2f4c8a8) (« hugo prise 5 », « prise 6 », « prise 9 », « prise 11 »), puis [`64faf65`](https://github.com/sebastienmarley/dkorpos/commit/64faf65)
- **Issue :** aucune

**Problème :** GitHub Pages n'est offert que sur un dépôt public, le workflow Hugo ne déployait rien et le thème LoveIt refusait de s'intégrer. Une dizaine de tentatives de déploiement en une journée, et le projet Hugo jeté et recommencé 4 fois en 2 jours.

**Résolution :** réécriture du workflow GitHub Actions, réorganisation des dossiers ([`a7defa9`](https://github.com/sebastienmarley/dkorpos/commit/a7defa9)), puis une génération à neuf du site avec le thème ([`2f4c8a8`](https://github.com/sebastienmarley/dkorpos/commit/2f4c8a8)). La première page est en ligne le 22 septembre.

### 2. L'IA qui réécrit mes textes
- **Date :** 22 septembre 2026
- **Commits :** [`ff81acd`](https://github.com/sebastienmarley/dkorpos/commit/ff81acd), [`3a005dd`](https://github.com/sebastienmarley/dkorpos/commit/3a005dd) (« rewrite de mon texte que l'IA a réécrit sans demander »)
- **Issue :** aucune

**Problème :** en voulant aligner Pages et Hugo, l'agent a charcuté, dépersonnalisé et simplifié mes premières entrées, à deux reprises.

**Résolution :** réécriture à la main, et une règle pour la suite : je n'écris plus mes textes à travers l'IA, et je ne la laisse plus toucher à mon contenu sans le demander.

### 3. La fondation du projet refaite trois fois
- **Date :** 23 septembre 2026
- **Commits :** [`e030d32`](https://github.com/sebastienmarley/dkorpos/commit/e030d32), [`ed357b3`](https://github.com/sebastienmarley/dkorpos/commit/ed357b3) (« Delete dkor directory »), [`e85ba7e`](https://github.com/sebastienmarley/dkorpos/commit/e85ba7e), [`8728f1c`](https://github.com/sebastienmarley/dkorpos/commit/8728f1c), [`2ff9331`](https://github.com/sebastienmarley/dkorpos/commit/2ff9331)
- **Issue :** aucune

**Problème :** le premier `laravel new` a été supprimé et recommencé. La 3e version est entrée en conflit avec le main, et les tests CI ne tournaient pas sur la bonne version de PHP.

**Résolution :** fusion du main, commit du « reste du commit post conflit », et workflow des tests CI passé à PHP 8.5.

### 4. Le `dir="rtl"` qui inversait tout
- **Date :** 25 septembre 2026 (introduit le 23 septembre dans [`e85ba7e`](https://github.com/sebastienmarley/dkorpos/commit/e85ba7e))
- **Commit :** [`b16b0af`](https://github.com/sebastienmarley/dkorpos/commit/b16b0af)
- **Issue :** [#1 User index](https://github.com/sebastienmarley/dkorpos/issues/1)

**Problème :** dans l'index des usagers, tout était aligné à droite et l'ordre des éléments était inversé : le nom de l'usager se retrouvait complètement à droite. L'IA soutenait dur comme fer qu'il suffisait de vider les caches et les vues.

**Résolution :** la cause était dans le code. Un attribut `dir="rtl"` de Flux, suggéré au tout début pour placer la navigation à droite, ordonnait toute la page à partir de la droite. Il a été retiré.

### 5. Oublier le pull après un merge
- **Date :** 26 septembre 2026, puis de nouveau le 25 octobre
- **Commit :** [PR #9](https://github.com/sebastienmarley/dkorpos/pull/9) ([`86b13f7`](https://github.com/sebastienmarley/dkorpos/commit/86b13f7))
- **Issue :** [#11 Gestion de l'horaire des employés](https://github.com/sebastienmarley/dkorpos/issues/11)

**Problème :** après avoir fusionné la branche de l'horaire, j'ai continué de travailler sans faire de pull, sur une version périmée. Le 25 octobre, j'ai encore failli écraser une modification de la même façon.

**Résolution :** un pull après chaque merge. GitHub me rappelle à l'ordre, et GitHub Desktop m'a permis de mettre mes changements de côté (« stash ») le temps de me resynchroniser.

### 6. Les tests CI cassés par l'enum des rôles
- **Date :** 26 et 27 septembre 2026
- **Commits :** [`898243f`](https://github.com/sebastienmarley/dkorpos/commit/898243f), [`f67fcfe`](https://github.com/sebastienmarley/dkorpos/commit/f67fcfe), [`ff426e7`](https://github.com/sebastienmarley/dkorpos/commit/ff426e7), [`14e59d5`](https://github.com/sebastienmarley/dkorpos/commit/14e59d5), [`5b58065`](https://github.com/sebastienmarley/dkorpos/commit/5b58065), [`35cf83b`](https://github.com/sebastienmarley/dkorpos/commit/35cf83b), [`ab8a99e`](https://github.com/sebastienmarley/dkorpos/commit/ab8a99e), [`92c3924`](https://github.com/sebastienmarley/dkorpos/commit/92c3924), [`0e78e59`](https://github.com/sebastienmarley/dkorpos/commit/0e78e59), fusionnés par la [PR #23](https://github.com/sebastienmarley/dkorpos/pull/23)
- **Issue :** [#5 Gestion des rôles](https://github.com/sebastienmarley/dkorpos/issues/5)

**Problème :** en passant les rôles à un enum, j'ai retiré le rôle `user`, que la factory d'usager utilisait encore. S'y ajoutaient des tests avec des rôles codés en dur et un filtre brisé. Une dizaine de commits « correction CI » en deux jours : j'ai révisé les tests plus souvent que le code, et les assertions proposées par l'IA étaient souvent erronées ou incomplètes.

**Résolution :** factory corrigée, tests codés en dur corrigés, puis le filtre. J'ai appris à challenger l'IA sur ses assertions.

### 7. Le premier jour d'horaire
- **Date :** 27 septembre 2026
- **Commit :** [`5dc0695`](https://github.com/sebastienmarley/dkorpos/commit/5dc0695)
- **Issue :** [#11 Gestion de l'horaire des employés](https://github.com/sebastienmarley/dkorpos/issues/11)

**Problème :** un bogue sur le `first_day` de l'horaire, fort probablement celui que j'ai remarqué en tournant la vidéo de survol de l'application ce jour-là.

**Résolution :** correction de `first_day`, ajout de la notion de `last_day` et d'une tâche cron qui désactive l'employé après sa dernière journée.

### 8. Deux rendez-vous pris en même temps
- **Date :** 28 septembre 2026
- **Commit :** [`0a8adc6`](https://github.com/sebastienmarley/dkorpos/commit/0a8adc6)
- **Issue :** [#22 Prise de RDV simultané](https://github.com/sebastienmarley/dkorpos/issues/22)

**Problème :** les rendez-vous étaient affichés dans une table HTML statique, et deux usagers pouvaient entrer en conflit en réservant la même plage.

**Résolution :** refactorisation des rendez-vous vers une table dynamique en Alpine.js, avec correction des conflits entre usagers.

## Octobre 2026

### 9. Les tests qui passaient « faussement »
- **Date :** 3 octobre 2026
- **Commits :** [`b5c75b5`](https://github.com/sebastienmarley/dkorpos/commit/b5c75b5), [`bd82f9f`](https://github.com/sebastienmarley/dkorpos/commit/bd82f9f), [`631be1f`](https://github.com/sebastienmarley/dkorpos/commit/631be1f), fusionnés par la [PR #31](https://github.com/sebastienmarley/dkorpos/pull/31)
- **Issue :** [#5 Gestion des rôles](https://github.com/sebastienmarley/dkorpos/issues/5)

**Problème :** la `UserFactory` utilisait `salesman` comme rôle par défaut. En refaisant les permissions, ce rôle a perdu presque tous ses droits, et des centaines de tests ont échoué. J'ai réalisé que, depuis l'introduction des permissions, les tests passaient seulement parce que le vendeur avait accès à tout. S'y ajoutait une permission ajoutée au code, mais oubliée dans le seeder.

**Résolution :** correction des assertions qui supposent un rôle « admin » et ajout de la permission au seeder. La remise en ordre complète des permissions s'est faite le 10 octobre (entrée 13).

### 10. Construire la vente dans l'index au lieu du show
- **Date :** 7 octobre 2026
- **Commit :** [`dcedf2d`](https://github.com/sebastienmarley/dkorpos/commit/dcedf2d), fusionné par la [PR #58](https://github.com/sebastienmarley/dkorpos/pull/58)
- **Issue :** [#13 Les Ventes](https://github.com/sebastienmarley/dkorpos/issues/13)

**Problème :** depuis la veille, je bâtissais la commande client dans `index.blade`. Il aurait été impossible de travailler sur deux commandes en même temps, une catastrophe si je l'avais découvert plus tard.

**Résolution :** passage dans le `show/{id}` dès la création d'une commande, avec énormément de déplacements de code, et création du composant `customerSearch` pour réutiliser la recherche de client partout.

### 11. Une recherche de client copiée au lieu d'un composant
- **Date :** 8 octobre 2026
- **Commits :** [`08098c9`](https://github.com/sebastienmarley/dkorpos/commit/08098c9), [`cee4ba5`](https://github.com/sebastienmarley/dkorpos/commit/cee4ba5)
- **Issue :** [#14 Modifier la requête de recherche d'un client](https://github.com/sebastienmarley/dkorpos/issues/14)

**Problème :** la recherche et l'ajout de client auraient dû être un composant dès leur création dans les rendez-vous. L'IA avait plutôt fabriqué du code copiable ailleurs dans l'application.

**Résolution :** un seul modèle de recherche, développé dans la commande client et appliqué aux rendez-vous, avec une colonne normalisée (sans casse, sans accents), reprise ensuite pour les usagers et les clients.

### 12. La substitution qui ne recalculait pas le prix
- **Date :** 8 octobre 2026
- **Commit :** [`0173946`](https://github.com/sebastienmarley/dkorpos/commit/0173946)
- **Issue :** [#13 Les Ventes](https://github.com/sebastienmarley/dkorpos/issues/13)

**Problème :** quand un fournisseur remplace un produit par un autre, le nouveau produit gardait le prix vendant de l'ancien. La veille, j'avais aussi bloqué en pleine vidéo de démonstration : impossible de savoir qu'un produit est substitué si la commande n'a pas été soumise au fournisseur.

**Résolution :** le prix vendant est recalculé lors de la substitution, et l'étape de soumission fait partie du parcours.

### 13. Le schéma SQL et les permissions dans les migrations
- **Date :** 10 octobre 2026
- **Commits :** [`605a637`](https://github.com/sebastienmarley/dkorpos/commit/605a637), [`8fd0ab4`](https://github.com/sebastienmarley/dkorpos/commit/8fd0ab4), [`ed2bebe`](https://github.com/sebastienmarley/dkorpos/commit/ed2bebe), [`801bb0e`](https://github.com/sebastienmarley/dkorpos/commit/801bb0e)
- **Issue :** aucune (décisions notées dans les ADR-0006 et ADR-0007)

**Problème :** pour documenter les tables, je génère le schéma avec `schema:dump --prune`. Or les tests CI dépendaient du dump initial et de 129 migrations, dont celles qui créaient les permissions : une fois les migrations élaguées, les tests échouaient.

**Résolution :** les permissions sont retournées dans le seeder, comme je le voulais au départ. Les tests se basent maintenant sur `config/access`, deux commandes artisan permettent d'ajouter des permissions aux rôles, et les seeders roulent dans une seule transaction pour ne pas rester bloqué à mi-chemin.

### 14. Le journal de mouvements effacé en cascade
- **Date :** 10 octobre 2026
- **Commit :** [`4c96711`](https://github.com/sebastienmarley/dkorpos/commit/4c96711)
- **Issue :** [#51 Comment savoir ce qui s'est passé avec un produit](https://github.com/sebastienmarley/dkorpos/issues/51)

**Problème :** en écrivant l'ADR sur les tables de données, j'ai découvert que supprimer un produit effaçait en cascade tout son journal de mouvements.

**Résolution :** le journal n'est plus supprimé avec le produit.

### 15. Les méthodes de paiement du marchand liées à celles des clients
- **Date :** 10 octobre 2026
- **Commit :** [`7d5159d`](https://github.com/sebastienmarley/dkorpos/commit/7d5159d)
- **Issue :** [#27 Système de gestion des paiements](https://github.com/sebastienmarley/dkorpos/issues/27)

**Problème :** je voulais gérer les méthodes avec lesquelles le magasin paie ses fournisseurs. L'IA a plutôt lié ces méthodes à celles que les clients utilisent pour payer.

**Résolution :** un bon recadrage, et une gestion séparée des méthodes de paiement du marchand.

### 16. L'argent en float
- **Date :** 11 octobre 2026
- **Commits :** [`d5b2ec7`](https://github.com/sebastienmarley/dkorpos/commit/d5b2ec7), [`4b88feb`](https://github.com/sebastienmarley/dkorpos/commit/4b88feb), [`1e09bf6`](https://github.com/sebastienmarley/dkorpos/commit/1e09bf6), [`b48370c`](https://github.com/sebastienmarley/dkorpos/commit/b48370c), [`96430b5`](https://github.com/sebastienmarley/dkorpos/commit/96430b5), [`785b815`](https://github.com/sebastienmarley/dkorpos/commit/785b815), [`f2368b3`](https://github.com/sebastienmarley/dkorpos/commit/f2368b3), fusionnés par la [PR #77](https://github.com/sebastienmarley/dkorpos/pull/77)
- **Issue :** [#76 La gestion de l'argent en float vs int](https://github.com/sebastienmarley/dkorpos/issues/76)

**Problème :** depuis le début, l'argent était un float arrondi à deux décimales. C'est imprécis : un bogue « fantôme » qui se produit peut-être une fois sur mille calculs, et qui aurait fini par devenir un vrai blocage.

**Résolution :** un cast `money` créé avec `make:cast`, et tous les montants stockés en entiers dans la BD. Le chantier s'est fait en 7 étapes : produits, ventes, facturation, fournisseurs, commandes client, paie, puis les derniers coûts épars, avec la migration de toutes les tables concernées.

### 17. Un modèle d'horaire mal conçu
- **Date :** 15 octobre 2026
- **Commits :** [`3213789`](https://github.com/sebastienmarley/dkorpos/commit/3213789), [`b24ad22`](https://github.com/sebastienmarley/dkorpos/commit/b24ad22), [`ef11c48`](https://github.com/sebastienmarley/dkorpos/commit/ef11c48), fusionnés par la [PR #81](https://github.com/sebastienmarley/dkorpos/pull/81)
- **Issue :** [#21 Comment gérer une modification simultanée ?](https://github.com/sebastienmarley/dkorpos/issues/21)

**Problème :** la semaine type s'appliquait à l'horaire complet : trop contraignant, pas pratique, mal conçu. Et deux personnes pouvaient modifier le même horaire en même temps, par exemple le directeur qui change une absence pendant que la comptable ferme la semaine de paie.

**Résolution :** une protection contre la modification simultanée, et un modèle réécrit qui attribue des quarts types à un employé à la fois.

### 18. Les catégories et départements à l'envers
- **Date :** 17 octobre 2026
- **Commit :** [`577ddf6`](https://github.com/sebastienmarley/dkorpos/commit/577ddf6), fusionné par la [PR #82](https://github.com/sebastienmarley/dkorpos/pull/82)
- **Issue :** [#79 Documenter et classifier un produit](https://github.com/sebastienmarley/dkorpos/issues/79) (et [#24](https://github.com/sebastienmarley/dkorpos/issues/24), [#25](https://github.com/sebastienmarley/dkorpos/issues/25))

**Problème :** une dépendance bizarre entre catégorie et département causait des bogues dans les liens entre les deux, réparties sur plusieurs menus.

**Résolution :** des départements qui ont des catégories, et une gestion unique par onglets (couleur, département, catégorie et le nouveau style), avec restructuration du schéma.

### 19. Une étoile au lieu d'un trait d'union
- **Date :** 18 et 19 octobre 2026
- **Commit :** [`d623120`](https://github.com/sebastienmarley/dkorpos/commit/d623120)
- **Issue :** aucune

**Problème :** échec du build du blogue, signalé par courriel juste avant de me coucher. Dans une date, j'avais tapé un `*` au lieu d'un `-`, une faute que je fais souvent sur le clavier Mac.

**Résolution :** correction de la date et nouveau déploiement.

### 20. Le multi-magasin ajouté après coup
- **Date :** 24 octobre 2026
- **Commits :** [`11c0270`](https://github.com/sebastienmarley/dkorpos/commit/11c0270), [`a7f6a1f`](https://github.com/sebastienmarley/dkorpos/commit/a7f6a1f), [`2765182`](https://github.com/sebastienmarley/dkorpos/commit/2765182), [`9eadf70`](https://github.com/sebastienmarley/dkorpos/commit/9eadf70), [`363e7d3`](https://github.com/sebastienmarley/dkorpos/commit/363e7d3), [`3ed0892`](https://github.com/sebastienmarley/dkorpos/commit/3ed0892), [`06fb507`](https://github.com/sebastienmarley/dkorpos/commit/06fb507), [`ccdda11`](https://github.com/sebastienmarley/dkorpos/commit/ccdda11), fusionnés par les [PR #102](https://github.com/sebastienmarley/dkorpos/pull/102) et [PR #105](https://github.com/sebastienmarley/dkorpos/pull/105) (la branche bien mal nommée `permissions`)
- **Issues :** [#100 Comment gérer l'inventaire par magasin](https://github.com/sebastienmarley/dkorpos/issues/100), [#103 Échanger de l'inventaire entre mes magasins](https://github.com/sebastienmarley/dkorpos/issues/103), [#32 Qu'arrive-t-il si j'ajoute un nouvel emplacement ?](https://github.com/sebastienmarley/dkorpos/issues/32)

**Problème :** l'inventaire appartenait à l'entreprise, alors que j'avais introduit le concept de magasin bien avant. Le rendre réel a brisé de nombreuses logiques. Par exemple, les horaires n'avaient aucun lien avec le magasin, si bien que tous les employés étaient sur le même horaire. En plus, l'IA a halluciné des besoins et documenté les changements d'horaire dans l'ADR-0001 sur les permissions. Et bien sûr, une grande quantité de tests CI ont cassé.

**Résolution :** retour en arrière sur l'ADR-0001 et création de l'ADR-0013. Ensuite, chaque module a été retesté avec deux usagers : un admin qui a `stores.all` et un directeur de l'autre succursale qui ne l'a pas. J'ai ajouté des sélecteurs de magasin, les notions de magasin physique et virtuel, et celle de « livré / facturé » pour utiliser le bureau chef comme adresse de facturation.

### 21. Le 2FA et le bouton hors composant
- **Date :** 25 octobre 2026
- **Commit :** [`2d40eb6`](https://github.com/sebastienmarley/dkorpos/commit/2d40eb6)
- **Issue :** [#107 Comment rendre l'application accessible de partout](https://github.com/sebastienmarley/dkorpos/issues/107)

**Problème :** le 2FA de Fortify fonctionnait, mais la boucle menait aux réglages de l'usager avec l'accès au menu latéral. J'ai isolé le réglage initial dans un layout personnalisé, et le bouton a cessé de fonctionner.

**Résolution :** le layout partiel commençait par un `@if`, si bien que son contenu était considéré comme faisant partie de l'en-tête : le bouton se retrouvait hors du composant. Les `@if` doivent se trouver à l'intérieur d'un `div`.

### 22. Le dossier database effacé et le passage à PostgreSQL
- **Date :** 25 octobre 2026
- **Commits :** [`36c3891`](https://github.com/sebastienmarley/dkorpos/commit/36c3891), [`e33c0be`](https://github.com/sebastienmarley/dkorpos/commit/e33c0be), fusionnés par la [PR #114](https://github.com/sebastienmarley/dkorpos/pull/114)
- **Issue :** [#107 Comment rendre l'application accessible de partout](https://github.com/sebastienmarley/dkorpos/issues/107)

**Problème :** pour passer au Cloud, il fallait quitter SQLite. En configurant DBNGIN, j'ai effacé le dossier `database` (factories, migrations, seeders et schéma), et ma BD SQLite avec, puisqu'elle tient dans un fichier hors du git. Ensuite, la conversion des migrations a exigé de nombreuses corrections, et la commande de démarrage faisait échouer les tests faute d'importer Carbon.

**Résolution :** restauration des fichiers grâce à GitHub, une migration par table sans les données, correction des tests CI, puis ajout de Carbon dans la commande.

### 23. Créer le premier admin sans terminal
- **Date :** 25 octobre 2026
- **Commits :** [`8737af8`](https://github.com/sebastienmarley/dkorpos/commit/8737af8), [`2ea82fb`](https://github.com/sebastienmarley/dkorpos/commit/2ea82fb), [`58ded34`](https://github.com/sebastienmarley/dkorpos/commit/58ded34)
- **Issue :** [#113 Données initiales : avec quoi démarre la production ?](https://github.com/sebastienmarley/dkorpos/issues/113)

**Problème :** la commande qui crée le premier admin et son magasin supposait un terminal, que Laravel Cloud n'offrait pas dans mon cas.

**Résolution :** la commande peut être lancée directement dans Laravel Cloud, et elle ne fonctionne que sur un déploiement frais, sans usager.

### 24. Le déploiement en production
- **Date :** 25 et 26 octobre 2026
- **Commits :** [`43d0acf`](https://github.com/sebastienmarley/dkorpos/commit/43d0acf), [`55916d1`](https://github.com/sebastienmarley/dkorpos/commit/55916d1), [`2378799`](https://github.com/sebastienmarley/dkorpos/commit/2378799), [`b94c073`](https://github.com/sebastienmarley/dkorpos/commit/b94c073), fusionnés par les [PR #118](https://github.com/sebastienmarley/dkorpos/pull/118) à [#121](https://github.com/sebastienmarley/dkorpos/pull/121)
- **Issues :** [#107](https://github.com/sebastienmarley/dkorpos/issues/107), [#116 Files d'attente et planificateur](https://github.com/sebastienmarley/dkorpos/issues/116), [#117 Sauvegardes](https://github.com/sebastienmarley/dkorpos/issues/117)

**Problème :** une journée complète, près de 8 heures, pour envoyer l'application locale dans le Cloud. Il a fallu trouver où activer le planificateur et le worker, construire la BD et la lier. Il n'y a pas de serveur PostgreSQL au Canada sur la plateforme, un enjeu pour la loi 25. Chaque PR dans le main déclenchait un déploiement, Vite était resté à sa version par défaut, et le crochet de déploiement était mal configuré dans Laravel Cloud.

**Résolution :** un flux `develop` (préprod) → `main` (prod), et un déploiement par crochet protégé, réparé le lendemain avec l'aide de mon collègue. Vite est passé de 0.3 à 1.4.0. Pour la loi 25, j'aurais repassé à MySQL dans un contexte réel ; ici, la décision est notée dans l'ADR-0014. Les tests de sauvegarde (dump) ont été complétés.

### 25. Le verrou de session, épisode 1 : contourner le NIP
- **Date :** 26 octobre 2026
- **Commits :** [`d0ec255`](https://github.com/sebastienmarley/dkorpos/commit/d0ec255), [`d5bd456`](https://github.com/sebastienmarley/dkorpos/commit/d5bd456), fusionnés par la [PR #123](https://github.com/sebastienmarley/dkorpos/pull/123)
- **Issue :** [#122 Verrouiller une session inactive](https://github.com/sebastienmarley/dkorpos/issues/122)

**Problème :** ce qui m'apparaissait comme un « quick fix » ne l'était pas. Le bouton de recul, le rafraîchissement, la réouverture dans un nouvel onglet ou la fermeture du navigateur permettaient de passer par-dessus le NIP. Et l'écran de verrouillage se retrouvait sous les modaux.

**Résolution :** le serveur garde l'état verrouillé et refuse toute requête tant que le NIP n'est pas saisi. La méthode a été réécrite deux fois pour passer par-dessus les modaux tout en préservant leur état dans Livewire. Le choix est expliqué dans l'ADR-0015.

### 26. La navigation mobile atroce
- **Date :** 31 octobre 2026
- **Commits :** [`6562d7b`](https://github.com/sebastienmarley/dkorpos/commit/6562d7b), [`1c8507e`](https://github.com/sebastienmarley/dkorpos/commit/1c8507e), [`55affce`](https://github.com/sebastienmarley/dkorpos/commit/55affce), fusionnés par la [PR #126](https://github.com/sebastienmarley/dkorpos/pull/126)
- **Issue :** [#124 Comment faire pour rendre l'interface accessible sur mobile](https://github.com/sebastienmarley/dkorpos/issues/124)

**Problème :** après le déploiement, j'ai ouvert l'application sur mon cellulaire : un choc, la navigation était absolument atroce. Je n'avais pas vraiment testé avec le mode de développement de Safari. Ensuite, la PR a échoué sur GitHub alors que les tests passaient en local, à cause de la façon de passer la liste des titres de la navigation.

**Résolution :** le menu latéral est remplacé par un tiroir, avec une recherche de type « Spotlight ». Les gabarits ont été adaptés aux formats iPhone et iPad, et la liste des titres a été corrigée dans `Navigation.php`.

### 27. Le verrou de session, épisode 2 : le jeton effacé par Livewire
- **Date :** 31 octobre 2026
- **Commit :** [`80e20c7`](https://github.com/sebastienmarley/dkorpos/commit/80e20c7)
- **Issue :** [#122 Verrouiller une session inactive](https://github.com/sebastienmarley/dkorpos/issues/122)

**Problème :** l'écran de verrouillage se déclenchait pendant que je travaillais, et il refusait mon NIP. Chaque requête réécrit la session entière telle qu'elle l'a lue. Une requête Livewire en cours, comme un `wire:poll`, effaçait donc un battement de cœur, un verrouillage ou un déverrouillage enregistré entre-temps. Mon environnement de développement, qui verrouille après une minute au lieu de 5, amplifiait le problème.

**Résolution :** l'activité et l'état du verrou sont gardés dans le cache, sous un jeton aléatoire rangé dans la session. Ce jeton ne change jamais, alors une copie périmée de la session le réécrit à l'identique. L'ADR-0015 a été révisé.

### 28. Les tests qui n'échouent qu'en PostgreSQL
- **Date :** 31 octobre et 7 novembre 2026
- **Commits :** [`a9d5476`](https://github.com/sebastienmarley/dkorpos/commit/a9d5476), [`d0568eb`](https://github.com/sebastienmarley/dkorpos/commit/d0568eb), [`0e95dd8`](https://github.com/sebastienmarley/dkorpos/commit/0e95dd8)
- **Issue :** aucune

**Problème :** SQLite renvoie les lignes dans l'ordre d'insertion, alors que PostgreSQL ne garantit aucun ordre sans tri. Des tests échouaient donc seulement dans le CI PostgreSQL, et j'ai dû les corriger un par un.

**Résolution :** des `orderBy` explicites. Le 7 novembre, Claude a enfin vérifié tous les tests d'un coup pour que ça ne se reproduise plus. Petit moment de rage intérieure : pourquoi pas la première fois ?

### 29. La PR faite sans `composer test`
- **Date :** 31 octobre 2026
- **Commit :** [`1d53439`](https://github.com/sebastienmarley/dkorpos/commit/1d53439), fusionné par la [PR #129](https://github.com/sebastienmarley/dkorpos/pull/129)
- **Issue :** aucune

**Problème :** la fatigue a eu raison de moi : PR sans `composer test`, et une douzaine d'erreurs sont apparues, toutes similaires et dues à mes changements dans les rendez-vous.

**Résolution :** corrections pour PHPStan. J'ai aussi appris la différence entre `php artisan test --compact` (mes tests Laravel en local) et `composer test`, qui ajoute PHPStan et un environnement proche de la production.

### 30. Le champ du magasin d'expédition jamais utilisé
- **Date :** 31 octobre 2026
- **Commit :** [`50b3f6f`](https://github.com/sebastienmarley/dkorpos/commit/50b3f6f), fusionné par la [PR #130](https://github.com/sebastienmarley/dkorpos/pull/130)
- **Issue :** [#124](https://github.com/sebastienmarley/dkorpos/issues/124) (passage de ménage du code mort)

**Problème :** en retirant le code mort, j'ai découvert que le champ du magasin d'expédition de la commande client n'était jamais utilisé. Il est pourtant essentiel : il indique si on peut expédier et, si oui, via quel entrepôt.

**Résolution :** le champ est branché, et une contrainte ajoutée pour la livraison et l'expédition.

## Novembre 2026

### 31. La comptabilité, plus grosse que prévu
- **Date :** 1er novembre 2026
- **Commits :** [`7554980`](https://github.com/sebastienmarley/dkorpos/commit/7554980), [`b98737c`](https://github.com/sebastienmarley/dkorpos/commit/b98737c), [`595f41d`](https://github.com/sebastienmarley/dkorpos/commit/595f41d), [`026c920`](https://github.com/sebastienmarley/dkorpos/commit/026c920), [`ad6dbc9`](https://github.com/sebastienmarley/dkorpos/commit/ad6dbc9), [`7747a6f`](https://github.com/sebastienmarley/dkorpos/commit/7747a6f), [`579d763`](https://github.com/sebastienmarley/dkorpos/commit/579d763), fusionnés par la [PR #133](https://github.com/sebastienmarley/dkorpos/pull/133)
- **Issues :** [#131 Comment m'assurer que ma comptabilité est exacte](https://github.com/sebastienmarley/dkorpos/issues/131), [#132 Payer mes fournisseurs](https://github.com/sebastienmarley/dkorpos/issues/132)

**Problème :** en rédigeant le User Story, j'ai réalisé que les grands livres exigeaient la refonte de tous les modules qui touchent à l'inventaire et à l'argent, alors que tout fonctionnait déjà. En plus, la valeur de l'inventaire n'était qu'un coût en USD, sans transport, douanes ni taux de change.

**Résolution :** ADR-0016, puis une seule fonction de transaction qui écrit dans les GL. Chaque achat, vente et paiement de facture génère maintenant une écriture, et le coût réel tient compte des frais et de la devise. S'ajoutent la conciliation bancaire et les écritures manuelles.

### 32. Les commissions et les primes à 0 $
- **Date :** 1er novembre 2026
- **Commit :** [`a1b0189`](https://github.com/sebastienmarley/dkorpos/commit/a1b0189)
- **Issue :** [#26 Gestion de la paie](https://github.com/sebastienmarley/dkorpos/issues/26)

**Problème :** en testant la paie avec le nouveau DemoDataSeeder, surprise : les commissions et les primes n'étaient que des placeholders fixés à 0 $. Le 4 octobre, la paie v1 attendait le module de ventes « pour brancher les calculs », et ce branchement n'avait jamais été fait.

**Résolution :** retrait du placeholder, et calcul branché dans `BuildPayrollReport`, avec tests.

### 33. Les notifications et la langue des tests
- **Date :** 7 novembre 2026
- **Commits :** [`1940049`](https://github.com/sebastienmarley/dkorpos/commit/1940049), [`aeb8bb7`](https://github.com/sebastienmarley/dkorpos/commit/aeb8bb7), fusionnés par la [PR #139](https://github.com/sebastienmarley/dkorpos/pull/139)
- **Issues :** [#20 Comment tenir informé un client](https://github.com/sebastienmarley/dkorpos/issues/20), [#135](https://github.com/sebastienmarley/dkorpos/issues/135) à [#138](https://github.com/sebastienmarley/dkorpos/issues/138)

**Problème :** les notifications touchent à tellement de modules que des dizaines de tests CI ont cassé. Des tests attendaient une date en français, et ils échouaient dans un environnement où `APP_LOCALE=en`.

**Résolution :** l'environnement de test est en français, et les tests ont été réécrits pour ne plus dépendre de la langue.

### 34. Le mot de passe aléatoire qui casse un test
- **Date :** 7 novembre 2026
- **Commit :** [`4ed9e39`](https://github.com/sebastienmarley/dkorpos/commit/4ed9e39) (« bogue intermittent de $@#%?%#? »), fusionné par la [PR #140](https://github.com/sebastienmarley/dkorpos/pull/140)
- **Issue :** aucune

**Problème :** un test échouait pour la première fois depuis le début, au hasard. Le mot de passe généré par `Str::password(12)` pouvait contenir `<`, `>` ou `\`, que la console interprétait comme des balises ou des échappements.

**Résolution :** le mot de passe n'est plus affiché avec `warn()`, mais échappé avec `OutputFormatter::escape()`.

### 35. Le blogue fusionné dans le main
- **Date :** 8 novembre 2026
- **Commits :** [PR #148](https://github.com/sebastienmarley/dkorpos/pull/148) ([`38e4c67`](https://github.com/sebastienmarley/dkorpos/commit/38e4c67)), corrigée par la [PR #149](https://github.com/sebastienmarley/dkorpos/pull/149) ([`9e19808`](https://github.com/sebastienmarley/dkorpos/commit/9e19808))
- **Issue :** [#147 Hors-projet, refonte du blog](https://github.com/sebastienmarley/dkorpos/issues/147)

**Problème :** après la refonte du blogue (retrait de LoveIt), j'ai fusionné ma branche directement dans le main, sans réfléchir. La branche develop était corrompue, mes fichiers de contenu de la veille perdus, 70 à 80 fichiers dans le « stash » en quelques secondes, et GitHub Desktop, mon local et GitHub tous désynchronisés.

**Résolution :** la recette en quatre étapes du billet [Difficultés rencontrées]({{< relref "techno/difficulte.md#fusion-de-branche" >}}) :
1. Remettre le main dans develop par une PR.
2. Jeter les changements « stashed », qui n'étaient que des fichiers générés.
3. Faire un pull de main et de develop.
4. Récupérer le contenu perdu avec « Revert File » de VS Code.

Environ 30 minutes de manipulations.

### 36. Les brouillons perdus des semaines 6 et 7
- **Date :** 8 novembre 2026
- **Commit :** aucun (les brouillons n'avaient jamais été commités)
- **Issue :** aucune

**Problème :** mes brouillons de blogue des semaines 6 et 7 n'avaient jamais été commités ni sauvegardés, et VS Code ne les avait pas gardés en mémoire.

**Résolution :** les entrées ont été reconstruites par un résumé de Claude, à partir de mes journaux, avec un avertissement en tête de billet. Leçon : la sauvegarde automatique ne coûte rien.

### 37. Le CI PostgreSQL qui ne finit plus
- **Date :** 8 novembre 2026
- **Commits :** [`35b0a3b`](https://github.com/sebastienmarley/dkorpos/commit/35b0a3b), [`b0eafc8`](https://github.com/sebastienmarley/dkorpos/commit/b0eafc8), [`21da7f3`](https://github.com/sebastienmarley/dkorpos/commit/21da7f3), [`0999e20`](https://github.com/sebastienmarley/dkorpos/commit/0999e20), [`e811e3c`](https://github.com/sebastienmarley/dkorpos/commit/e811e3c) (« réparation de test prise 5 »), fusionnés par la [PR #150](https://github.com/sebastienmarley/dkorpos/pull/150)
- **Issue :** aucune

**Problème :** après une modification du seeder, les tests ont commencé à échouer, en plusieurs vagues :
- un client créé à la date du jour, ou pas, et un test qui recevait `NULL` ;
- la création aléatoire d'une commande client qui échouait ;
- le CI PostgreSQL qui ne finissait plus, sans aucun retour.

Les pistes se sont enchaînées. D'abord une lenteur extrême, parce que les vieilles tables n'avaient aucun index sur leurs clés étrangères : `foreignId()->constrained()` n'en crée pas en PostgreSQL ni en SQLite, mais SQLite reste 3 fois plus rapide même sans index. Ensuite l'horloge, GitHub en UTC et moi à Toronto. Rien ne se reproduisait en local, malgré le journal brut de GitHub.

**Résolution partielle :**
- un timeout de 15 minutes et une annulation des workflows précédents (`concurrency`) ;
- une migration qui ajoute les index des clés étrangères ;
- des corrections au seeder ;
- des traces dans le seeder, qui allongent le journal de 500 lignes mais permettront de retrouver le bobo.

Ça a passé, mais le problème restait aléatoire.

### 38. L'autovacuum de PostgreSQL
- **Date :** 9 novembre 2026
- **Commits :** [`4d183af`](https://github.com/sebastienmarley/dkorpos/commit/4d183af), [`b0c1749`](https://github.com/sebastienmarley/dkorpos/commit/b0c1749)
- **Issue :** aucune

**Problème :** la vraie cause des échecs aléatoires.
1. PostgreSQL utilise le MVCC : chaque test annulé par rollback laisse des lignes mortes.
2. L'autovacuum passe et note que les tables sont vides, et le planificateur choisit des plans pour des tables vides.
3. Le seeder de démo, environ 59 000 requêtes dans une seule transaction, se retrouve avec des requêtes de plus de 3 secondes chacune.

Tout dépendait de si l'autovacuum passait avant le test du seeder.

**Résolution :** l'autovacuum est désactivé sur la BD de test, ce qui retranche 90 secondes au CI PostgreSQL. J'ai aussi optimisé la suite : condensation de tests et retrait d'une cinquantaine de cas inutiles sur plus de 7 700 assertions.

### 39. Le grand ménage du dépôt
- **Date :** 14 novembre 2026
- **Commits :** [`cecbd65`](https://github.com/sebastienmarley/dkorpos/commit/cecbd65), [`571de63`](https://github.com/sebastienmarley/dkorpos/commit/571de63), [`71058ce`](https://github.com/sebastienmarley/dkorpos/commit/71058ce), [`d48e8c6`](https://github.com/sebastienmarley/dkorpos/commit/d48e8c6), [`da7ba8d`](https://github.com/sebastienmarley/dkorpos/commit/da7ba8d), [`424c915`](https://github.com/sebastienmarley/dkorpos/commit/424c915), fusionnés par la [PR #152](https://github.com/sebastienmarley/dkorpos/pull/152)
- **Issue :** [#151 Nettoyer le workflow et GH](https://github.com/sebastienmarley/dkorpos/issues/151)

**Problème :** le dépôt traînait plusieurs fichiers mal placés ou inutiles :
- `dependabot.yml` était dans `dkor/.github` au lieu de `.github`, donc inactif depuis le début ;
- des `.DS_Store` de Finder étaient dans le git ;
- une copie déployée du blogue (79 fichiers) était commitée ;
- un fichier yml de Hugo inutile datait de l'initialisation ;
- le schéma SQLite avait 11 migrations de retard ;
- le module `rollup` était resté d'un ancien CI.

**Résolution :** dependabot est déplacé, et il a ouvert sa première PR le jour même ([PR #153](https://github.com/sebastienmarley/dkorpos/pull/153)). Le `.gitignore` est mis à jour, le schéma régénéré, et les fichiers et Blade morts retirés.

### 40. Un filtre par produit impossible à atteindre
- **Date :** 14 novembre 2026
- **Commit :** [`59759b8`](https://github.com/sebastienmarley/dkorpos/commit/59759b8)
- **Issue :** [#155 Base Layout](https://github.com/sebastienmarley/dkorpos/issues/155)

**Problème :** le journal des mouvements d'inventaire avait un message préfait pour le tri par produit, mais aucun moyen de trier par produit. Il fallait trafiquer l'URL.

**Résolution :** un lien dans l'onglet inventaire du produit mène à `inventory/movements?product={id}`, selon la permission.

### 41. La ligne cliquable en JavaScript dans Flux
- **Date :** 14 et 15 novembre 2026
- **Commits :** [`dff9c53`](https://github.com/sebastienmarley/dkorpos/commit/dff9c53), [`684d492`](https://github.com/sebastienmarley/dkorpos/commit/684d492)
- **Issue :** [#161 Modification des tables des blade d'index](https://github.com/sebastienmarley/dkorpos/issues/161)

**Problème :** pour rendre une ligne de tableau cliquable, l'IA a écrit du JavaScript directement dans un composant Flux, ce qui ne fonctionne pas. Ce n'était pas la première fois.

**Résolution :** le JavaScript est remplacé par un `data-href` dans un composant `x-table.row-link`, réutilisé ensuite partout.

### 42. La recherche de commandes qui fouille les courriels
- **Date :** 15 novembre 2026
- **Commit :** [`b73a2ef`](https://github.com/sebastienmarley/dkorpos/commit/b73a2ef)
- **Issue :** [#161 Modification des tables des blade d'index](https://github.com/sebastienmarley/dkorpos/issues/161)

**Problème :** dans les commandes client, chercher « jean » proposait Aurélie. La recherche passe par un composant commun bâti dans le module des clients, et elle fouillait aussi le courriel, alors que les critères attendus sont le numéro de commande, le nom et le téléphone.

**Résolution :** le courriel est retiré de la recherche. Il reste un cas à régler : chercher la commande 103 trouve aussi tous les numéros de téléphone qui contiennent 103.

### 43. Les frais d'annulation non exigés
- **Date :** 15 novembre 2026
- **Commit :** [`2b4f87b`](https://github.com/sebastienmarley/dkorpos/commit/2b4f87b)
- **Issue :** [#158 Peaufiner la navigation XS](https://github.com/sebastienmarley/dkorpos/issues/158)

**Problème :** un client qui annule un produit en commande spéciale doit payer des frais. Mais si on lui vendait autre chose, ces frais n'étaient plus exigés au paiement.

**Résolution :** `requiredPaidTotal()` exige maintenant 100 % des frais d'annulation, comme le faisait déjà `recalculateBalance()` dans la vue. J'ai aussi ajouté un bouton pour payer le solde complet.

### 44. Le ramassage dans un magasin sans entrepôt
- **Date :** 15 novembre 2026
- **Commits :** [`6e0650b`](https://github.com/sebastienmarley/dkorpos/commit/6e0650b), [`0d140ef`](https://github.com/sebastienmarley/dkorpos/commit/0d140ef), [`ca72819`](https://github.com/sebastienmarley/dkorpos/commit/ca72819)
- **Issue :** aucune (correctif hors périmètre de [#158](https://github.com/sebastienmarley/dkorpos/issues/158))

**Problème :** un magasin dont l'entrepôt est ailleurs, comme une salle de montre, laissait ramasser le stock comme s'il était sur place. Il était impossible d'ouvrir la commande d'un autre magasin sans `stores.all`. Et le bureau chef, un magasin virtuel sans entrepôt, pouvait vendre et recevoir.

**Résolution :**
- le ramassage est retiré aux magasins qui ne sont pas leur propre entrepôt, avec un bandeau d'avertissement ;
- on peut ouvrir la commande d'un autre magasin, avec un badge qui indique sa provenance ;
- un magasin sans entrepôt ne peut plus vendre ni commander, et l'ADR-0013 est corrigé.

### 45. Le remboursement par n'importe quel mode de paiement
- **Date :** 15 novembre 2026
- **Commit :** [`888ddf6`](https://github.com/sebastienmarley/dkorpos/commit/888ddf6)
- **Issue :** aucune (correctif hors périmètre de [#154](https://github.com/sebastienmarley/dkorpos/issues/154))

**Problème :** un problème majeur que je n'avais jamais essayé : un remboursement pouvait se faire sur n'importe quel mode de paiement, peu importe comment le client avait payé.

**Résolution :** même mode de paiement, jusqu'au montant déboursé sur ce mode. La décision est notée dans l'ADR-0019, que j'aurais dû écrire bien plus tôt. J'ai aussi ajouté un GL pour le crédit client, et corrigé le DemoDataSeeder et les tests.

### 46. Les commandes « nouvelles » pour la vie
- **Date :** 15 novembre 2026
- **Commit :** [`e891c26`](https://github.com/sebastienmarley/dkorpos/commit/e891c26)
- **Issue :** [#154 Modification de l'interface utilisateur](https://github.com/sebastienmarley/dkorpos/issues/154)

**Problème :** une commande remboursée ou annulée gardait le statut « nouvelle » toute sa vie, ce qui ne voulait plus rien dire et nuisait au filtrage.

**Résolution :** les statuts « remboursée » et « annulée » sont ajoutés à l'enum.

### 47. Les `wire:key` manquants
- **Date :** 15 novembre 2026
- **Commit :** [`b452441`](https://github.com/sebastienmarley/dkorpos/commit/b452441)
- **Issues :** [#158](https://github.com/sebastienmarley/dkorpos/issues/158), [#165 Simplifier les modaux](https://github.com/sebastienmarley/dkorpos/issues/165)

**Problème :** entre deux enregistrements, le modal d'édition de ligne affichait des champs préremplis avec la mauvaise donnée. Quand les branches d'un même `@if`/`@else` placent des champs différents au même endroit, Livewire réutilise le champ d'une branche pour l'autre et garde son ancienne valeur.

**Résolution :** ajout des `wire:key`, puis une vérification de toute l'application : une dizaine de modules auraient pu présenter le même dysfonctionnement.

### 48. Le verrou de session, épisode 3 : le NIP qui s'écrit à l'envers
- **Date :** 15 novembre 2026
- **Commits :** [`89901c2`](https://github.com/sebastienmarley/dkorpos/commit/89901c2), [`b452441`](https://github.com/sebastienmarley/dkorpos/commit/b452441), [`ff818cf`](https://github.com/sebastienmarley/dkorpos/commit/ff818cf) (« 5e correction du verrou de session »)
- **Issues :** [#122](https://github.com/sebastienmarley/dkorpos/issues/122), [#158](https://github.com/sebastienmarley/dkorpos/issues/158)

**Problème :** le NIP a cessé d'être accepté, en mode conception adaptative dans Safari. Le curseur restait bloqué à gauche du champ, et chaque chiffre s'écrivait devant les autres (123456 devenait 654321). Trois correctifs dans la même journée :
1. Les champs NIP passent en texte masqué, pour bloquer le remplissage automatique.
2. `one-time-code` est ajouté pour la même raison.
3. Claude pointe la cloche de notification et son `wire:poll`. Je conteste : le poll ne roule qu'une fois par minute.

**Résolution :** « Vous avez raison », puis la vraie cause, une boucle sans fin dans l'écran de verrouillage lui-même.
- L'événement `close` du dialogue arrive après coup, quand `showLock()` l'a souvent déjà rouvert.
- Chaque `close` relançait donc `showLock()`, qui refermait et rouvrait le dialogue, reprenait le focus et remettait le curseur au début.
- Le correctif ajoute un test `! $el.open`, ne touche plus au dialogue si le focus est déjà dedans, et place le curseur à la fin du NIP.

Jamais je n'aurais trouvé ça en lisant le code.

### 49. Le journal d'inventaire qui charge tout
- **Date :** 21 novembre 2026
- **Commits :** [`d9c82d9`](https://github.com/sebastienmarley/dkorpos/commit/d9c82d9), [`07557c9`](https://github.com/sebastienmarley/dkorpos/commit/07557c9), [`be502f4`](https://github.com/sebastienmarley/dkorpos/commit/be502f4)
- **Issues :** [#163 Retrait du comportement de tables pré générées](https://github.com/sebastienmarley/dkorpos/issues/163), [#161](https://github.com/sebastienmarley/dkorpos/issues/161)

**Problème :** le journal d'inventaire générait tous les mouvements au chargement de la page. Avec 3 000 lignes, c'est instantané ; avec des millions, ce serait interminable. Les ajustements d'inventaire avaient le même défaut.

**Résolution :** plus de génération au démarrage, des index et un filtre d'âge. L'option archive est fixée à 1 000 jours, faute d'avoir construit le cycle d'inventaire. La pagination est simplifiée, et un filtre d'année est ajouté aux ajustements.

### 50. Les requêtes non typées au merge
- **Date :** 21 novembre 2026
- **Commit :** [`c9c1bde`](https://github.com/sebastienmarley/dkorpos/commit/c9c1bde), fusionné par la [PR #168](https://github.com/sebastienmarley/dkorpos/pull/168)
- **Issue :** [#154 Modification de l'interface utilisateur](https://github.com/sebastienmarley/dkorpos/issues/154)

**Problème :** au merge de la refonte visuelle dans develop, des tests CI ont échoué : certaines requêtes n'étaient pas typées, et PHP traduisait mal des libellés dans les tests.

**Résolution :** les requêtes sont typées dans les promotions, les mouvements d'inventaire et la commande client.

### 51. Livewire 4 et le `wire:model` qui ne parle plus au serveur
- **Date :** 22 novembre 2026
- **Commits :** [`6a7dcd5`](https://github.com/sebastienmarley/dkorpos/commit/6a7dcd5), [`ce4bc25`](https://github.com/sebastienmarley/dkorpos/commit/ce4bc25)
- **Issues :** [#164 Optimiser le mount et le render](https://github.com/sebastienmarley/dkorpos/issues/164), [#167 Refonte de produits](https://github.com/sebastienmarley/dkorpos/issues/167)

**Problème :** dans le modal d'ajout de produit, une fois le fournisseur choisi, le champ de recherche par liste de prix restait incliquable. Depuis Livewire 3, et toujours en Livewire 4, un `wire:model` sans `.live` ne prévient plus le serveur. Même chose pour `.blur` seul, qui ne synchronise que côté client à la sortie du champ. En prime, le champ du coût du produit faisait 20 requêtes par frappe.

**Résolution :** `.live` sur le sélecteur de fournisseur, et `.live.blur` sur 8 champs. Les 391 `wire:model` de l'application ont été vérifiés, et deux autres ont été corrigés (type de retour et code hexadécimal des couleurs). Le coût fait maintenant 9 requêtes au changement de champ au lieu de 20 par frappe. Avec l'affichage des seuls onglets actifs, la fiche produit passe de 29 à 13 requêtes, et la fiche usager de 32 à 9.

### 52. Le UserForm du tout premier jour
- **Date :** 22 novembre 2026
- **Commit :** [`8937bfa`](https://github.com/sebastienmarley/dkorpos/commit/8937bfa)
- **Issue :** aucune

**Problème :** le `UserForm`, bâti dès le 24 septembre et remplacé par la suite, traînait toujours dans le code avec ses tests. Le 4 octobre, je me demandais déjà si je me retrouverais avec du code mort sans le savoir, « comme mon user Form de départ ». Je n'osais jamais l'enlever.

**Résolution :** le composant, sa vue et ses tests sont retirés, après avoir vérifié que rien ne l'utilisait, factories comprises.

## Petits correctifs en vrac

| Date | Commit | Issue | Problème et résolution |
|---|---|---|---|
| 29 sept. | [`9a4edad`](https://github.com/sebastienmarley/dkorpos/commit/9a4edad) | — | Bogue de `flux:toast` corrigé pendant la refonte de Users (modal d'édition remplacé par un show). |
| 3 oct. | [`f2735bb`](https://github.com/sebastienmarley/dkorpos/commit/f2735bb) | [#6](https://github.com/sebastienmarley/dkorpos/issues/6) | Un produit pouvait coûter 0 $ : règle de coût ajoutée. |
| 3 oct. | [`ea8247a`](https://github.com/sebastienmarley/dkorpos/commit/ea8247a) | [#28](https://github.com/sebastienmarley/dkorpos/issues/28) | Le select de la commande fournisseur chargeait tout le catalogue : recherche par modèle ou ID. |
| 4 oct. | [`bd5b657`](https://github.com/sebastienmarley/dkorpos/commit/bd5b657) | — | Le magasin d'appartenance de l'usager n'était pas obligatoire : champ rendu obligatoire. |
| 6 oct. | [`f6d61b2`](https://github.com/sebastienmarley/dkorpos/commit/f6d61b2) | [#47](https://github.com/sebastienmarley/dkorpos/issues/47) | Trois vendeurs à 33 % deviennent 34/33/33, ce qui faisait planter le test : assertion corrigée. |
| 31 oct. | [`174e589`](https://github.com/sebastienmarley/dkorpos/commit/174e589) | — | Dans les modules à onglets, la sauvegarde ne se faisait qu'au bouton : elle se fait au changement d'onglet, et le code postal a un format forcé. |
| 1er nov. | [`e835512`](https://github.com/sebastienmarley/dkorpos/commit/e835512), [`a1b0189`](https://github.com/sebastienmarley/dkorpos/commit/a1b0189) | — | Le DemoDataSeeder révèle une erreur d'écriture comptable dans la commande client et un oubli dans la factory de produit. |
| 15 nov. | [`2b4f87b`](https://github.com/sebastienmarley/dkorpos/commit/2b4f87b) | [#158](https://github.com/sebastienmarley/dkorpos/issues/158) | Police floue sur cellulaire : police du navigateur, taille passée à 14-15 px. |
| 18 nov. | [`541eae0`](https://github.com/sebastienmarley/dkorpos/commit/541eae0) | [#155](https://github.com/sebastienmarley/dkorpos/issues/155) | Les champs de $ s'incrémentaient d'un sou à la fois : incrémenteur retiré en CSS. |

## Ce qui revient

En relisant le tout, trois thèmes reviennent sans cesse :
- **Les tests CI.** Ce sont les difficultés les plus fréquentes, et le plus souvent à cause de l'environnement (rôles, langue, ordre de tri, PostgreSQL) plutôt que du code de l'application.
- **Les décisions prises tard.** Le multi-magasin, l'argent en float, la comptabilité et les remboursements auraient coûté beaucoup moins cher avec un ADR écrit avant le code.
- **L'IA.** Elle accélère, mais elle invente parfois des besoins (ADR-0001, méthodes de paiement), réécrit mes textes et propose des explications plausibles mais fausses (le verrou de session), qu'il faut savoir contester.
