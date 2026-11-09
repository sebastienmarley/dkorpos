---
title: "Itération 3 - semaine 1"
date: 2026-10-26
draft: false
description: "Retour sur la dernière semaine de développement, les blocages rencontrés et les premiers constats."
categories:
  - "Projet"
tags:
  - "PHP"
  - "Laravel"
  - "IA"
  - "développement"
---

## Résumé de la semaine
Avertissement, la présente entrée de blog a été rédigé en me servant de mes entrées de journaux de la semaine via un résumé fait par Claude, j'ai perdu mes brouillons des semaines 6 et 7 qui n'ont jamais été commit et ne sont pas dans la "mémoire" de VSCode faute d'avoir été sauvegardé.  

Semaine qui débute avec le retour du jalon 2 : si les User Story étaient le défaut au premier jalon, cette fois c'est ce blogue, trop peu étoffé. Le jour 5, une rencontre avec mon professeur. Côté développement, j'ai complété le module de livraison et ses dépendances (User Story #93 à #96), ce qui vient compléter les modules de livraison et d'expédition. J'ai aussi revu l'application au complet pour qu'elle prenne en considération le multi magasin, incluant l'Issue #32 (l'ajout d'un emplacement) et le transfert inter magasin. En fin de semaine, j'ai tenté le passage en production, puis réparé le déploiement via crochet et entamé la gestion des sessions ouvertes (ADR-0015 et Issue #122).

## Leçons de la semaine

- Ne jamais assumer que ce que j'ai fait 20 fois sans erreur n'aura pas une coquille la 21e fois. Je n'étais pas remonté corriger mon push du dimanche soir.
- Comprendre un outil ne suffit pas, il faut comprendre à quoi il sert, comment il fonctionne et ce qu'il est dans l'univers du code. Comme j'ai repris ce que je connaissais, j'ai sauté une étape importante : la recherche d'information sur mes outils. Exemple : Eloquent est l'ORM utilisé par Laravel, Livewire est un framework full-stack qui permet d'écrire l'interface en PHP via des Blade sans passer par du Javascript, et Flux est mon coffre de gabarits pour les objets visuels.
- Les User Story et les ADR : le code est mieux structuré depuis et je rencontre très peu d'écueils en bâtissant l'application. Mes deux meilleurs chantiers, qui étaient aussi les deux plus gros, ont été réalisés en écrivant mes idées en prose puis en les faisant retranscrire dans un gabarit par l'IA.
- Trouver l'entre-deux : garder de la flexibilité dans le code sans développer de modules inutiles, simplement conserver du potentiel.
- Avec l'IA, j'ai utilisé une approche de guidage et d'explication plutôt que de faire rouler des commandes CLI sans en connaître les tenants et aboutissants.

## Les blocages rencontrés

**Le multi magasin.** Premier gros chantier lié à une erreur de conception : l'inventaire était pour l'entreprise, mais j'avais introduit le concept de magasin. Permettre d'ajouter des magasins est venu complexifier et chambouler une grande partie du code écrit. J'ai dû revenir en arrière sur plusieurs points et j'ai choisi d'aller de l'avant malgré le risque de casser le code, plutôt que d'avoir à tout reprogrammer une fois en production.

**Le passage en production.** J'ai eu l'impression de revivre ma première semaine du projet : nouvel environnement, nouvelles interfaces, construire, détruire, rebâtir, pour simplement rendre accessible de n'importe où ce qui fonctionne en local. Le déploiement via crochet n'était pas configuré de la bonne façon dans Laravel Cloud ; mon collègue m'a coaché pour le réparer.

**Le NIP et le verrouillage d'écran.** Ce qui m'apparaissait comme un « quick fix » ne l'était pas. Il a fallu protéger contre le bouton de recul, le rafraîchissement, la réouverture d'une page dans un nouvel onglet et la fermeture puis la réouverture du navigateur. J'ai réécrit la méthode deux fois pour éviter d'être sous les modaux tout en préservant leurs états dans Livewire. Il reste une limite : changer d'utilisateur fait perdre les données en cours de saisie. Voir le [gist](https://gist.github.com/sebastienmarley/6112123b886e9dcaa634eec8367d6ed7.js) pour cette session.

## Les progrès concrets

- Module de livraison complété ([gist de la journée](https://gist.github.com/sebastienmarley/035d5a2fdd62bff3f050b3bd103c7a2b.js)), Issue #93 à #96
- Module d'expédition complété
- Prise en charge du multi magasin dans l'ensemble de l'application
- Ajout d'un emplacement (Issue #32)
- Transfert inter magasin
- Première tentative de passage en production ([gist](https://gist.github.com/sebastienmarley/800ec527fc414001aef153e02e13113b.js)), avec des sub story pour les décisions plus importantes : 3 des 6 complétées
- Déploiement via crochet réparé
- Retour du self-serve pour réinitialiser son mot de passe
- ADR-0015 et Issue #122 : sessions ouvertes, NIP et verrouillage d'écran

## La semaine à venir

- Compléter les 3 sub story restantes du passage en production.
- Ouvrir les comptes S3, Postmark et Nightwatch, à la dernière minute pour éviter des coûts inutiles.
- Fermer l'application aux IP qui ne proviennent pas du Canada et des USA ?
