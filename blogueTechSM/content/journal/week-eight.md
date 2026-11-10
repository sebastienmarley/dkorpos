---
title: "Réflexion semaine 8"
date: 2026-11-07
draft: false
description: "."
categories:
  - "Project"
tags:
  - "journal"
  - "projet"
  - "réflexion"
---

## jour 5

Encore une semaine d'activité plus concentré durant mon weekend. Le projet tire à sa fin d'après moi. J'ai une livrée 100% fonctionnelle en production avec tout ce que je recherchais à développer. Je n'ai plus de nouveaux besoins, À ce stade je considère que l'application entrerait en version de "test beta" avec les usagers du magasin pour venir contre vérifier que les modules répondent à leur besoin et que le UI est agréable à utiliser. Je vais donc commettre mon application au Jalon 3 demain et je vais utiliser les prochaines semaines pour remplir le blogue et peaufiner les petits détails, car au final j'estime que l'application atteint le livrable du Jalon 4. Je vais construire une BD pour la production avec des fausses données étalées sur 90 jours pour permettre de vraiment tester l'environnement avec du "juice" comme dirait mon collègue. Dans mon local avec le DataBaseSeeder c'est très facile de construire une tonne de trucs, mais en production je ne suis pas trop certain de la façon optimale pour procéder. En tous les cas je dois créer un usager supplémentaire.   
Dans un autre ordre d'idée, les progrès du jour sont impressionants. Gabarits d'impression / courriel pour les commandes fournisseurs et clients. Système de notification client pour chacune des étapes de leur commande, peu importe l'issu de celle-ci, soit ramassage, livraison, expédition. Ensuite, les promotions, liquidations et vente démonstrateur, cette dernière portion me donne envie d'extraire de produit pour l'envoyer dans sa propre gestion des démos. Cela pourrait faciliter le mouvement d'inventaire, ce serait plus intuitif et je pourrais gérer des exceptions et introduire le sélecteur de magasin plus facilement.  
Un peu moins facile les déploiements et les tests CI, heureusement toutefois le workflow fonctionne vraiment bien et je n'ai jamais d'erreur dans mon LaravelCloud en lien avec les déploiement, l'utilisation de la branche develop pour m'assurer que tout passe sur GH vient réduire les risques de problème et avec le crochet d'appel je m'assure de toujours pousser une version stable.  

Deux nouveaux ADR aussi, un pour les promo, l'autre pour les démos et liquidation. Je ne trouvais pas pertinent d'en faire un pour la messagerie, mais je peux imaginer que dans un monde idéal il m'aurait fallu des dizaines d'ADR supplémentaire. Je vois la différence à chaque fois que je démarre un agent, plus les ADR se densifie meilleur l'agent interagit avec mon application. Je n'ose pas imaginer ce qu'il et possible de faire avec des modèles d'agent plus récent et performant. 

## Jour 6

Journée consacrée au blog, journal, récupération et rattrapage. J'ai perdu des brouilons de mes semaines précédentes et j'ai utilisé l'IA pour reconstruire un résumé via mes journaux, mais cependant je n'aime pas ce que ça donne, mais je vais faire avec. Mes transcriptions ont été déposés dans les gists et postés, mon blog a son tout nouveau format et je crois que mon contenu est rétabli. J'ai quand même eu [une légère frousse]({{< relref "techno/difficulte.md#fusion-de-branche" >}}), ce matin, mais au final tout est revenu comme hier soir. J'ai perdu des brouillons, ce n'est pas à mon goût, mais je crois que ça fait parti de mes apprentissages, la technologie me permet de faire des sauvegardes automatiques, pourquoi me passer de cette option qui ne coûte rien ?  

## Jour 7

Dépot du jalon 3, puisqu'hier je n'arrivais pas à corriger ma CI de PostgreSQL. Ensuite, je me suis attaqué, avec Fable, à comprendre le problème, la raison littéralement le fonctionnement même de PostgreSQL. Il a donc fallu empêcher la fonction de nettoyage de s'exécuter pour m'assurer que mes tests fonctionnent comme désirés. Je viens d'ajouter d'autres lectures personnels à ma liste suite à cela, il est vrai que mon cours sur les BD remonte à plus de deux ans déjà, mais bon. J'ai profité de la promo sur Fable pour tenter d'optimiser les tests CI, gain de 2 secondes au final sur le reste, étonnament la portion création de tests CI est solide, au point de n'avoir qu'une 50 aine de cas inutile sur les 7700+ assertion des 1650+ test. 