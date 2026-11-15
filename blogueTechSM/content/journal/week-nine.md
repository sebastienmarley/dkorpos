---
title: "Réflexion semaine 9"
date: 2026-11-15
draft: false
description: "."
categories:
  - "Project"
tags:
  - "journal"
  - "projet"
  - "réflexion"
---

## Jour 5

J'ai consacré ma journée à la réécriture de l'interface utilisateur et à l'épuration du git. J'ai donc entrepris d'estimer l'ampleur des travaux via Issue #154 que j'ai découpé en 12 sub-issue. J'ai l'intention de compléter ceux-ci d'ici la fin de la prochaine semaine. Avec un démarrage en douceur aujourd'hui.
### Apprentissage du jour
Pourquoi le fichier de structure de base se nomme sidebar.blade.php ?
C’est la convention du starter kit Livewire de Laravel, d’où vient sans doute le projet. Le nom sidebar ne veut pas dire « le composant sidebar ». Il désigne une variante de layout : une mise en page complète construite autour d’une barre latérale. Ce découpage est donc normal, même si le nom prête à confusion.  
Merci Claude
J'ai eu quelques minutes d'interrogation, j'étais persuadé que c'était app.blade.php, mais, évidemment, ça c'est sur l'appli de mon employeur. 
### Remettre l'application en ordre
Dernièrement, je me cherche un peu plus, je crois qu'un blade est dans tel dossier parce ce que l'application de mon travail est ordonné d'une façon, mais mon application est ordonné selon les conventions établi avec la fonction Laravel new, les controller ne sont pas fait de la même façon. Je fais trop de révision de code et de déboguage, en plus nous déployons dans 3 semaines soit pile avec la fin du projet ici. Alors, au final je suis dans VS Code sans arrêt. J'ai même pensé passer à Cursor, mais l'interface ne m'est pas suffisament familière.  
Peu importe, j'ai pris note du commentaire sur les fichiers et dossiers de trop, c'est encore une fois mon inexpérience qui ressort, j'en ai profiter pour me faire expliquer ce que c'était et prendre note pour mon prochain projet. Point positif, j'ai trouvé dependabot qui était dans le mauvais dossier, donc inactif depuis le début, un autre outil dont je me suis privé, sans trop savoir. Avec Claude j'ai ensuite entamé le processus de nettoyage, un survol complet de l'application pour retirer les fichiers et dossiers inutiles. Ce qui a entrainé le retrait d'une copie déployée du blogue (voir issue #151), donc 79 fichiers supprimé, modification du gitignore pour éviter que ça se répète, idem avec les fameux DS_Store et j'ai aussi retiré un fichier yml de Hugo qui était inutile, datant de l'initialisation du blogue.  
La semaine dernière (9 novembre) via le commit b0c1749 j'ai aussi allégé les tests CI et procédé à d'autre nettoyage. J'apprécie pouvoir lire et comprendre le code, donc c'est important pour moi, surtout avant la transformation visuelle, de mettre de l'ordre dans ce qui existe. La prochaine étape étant de mettre de l'ordre dans ce que l'usager voit, déjà entamé le 31 octobre via de nombreux commit (ex : 6562d7b, 1c8507e, ffc021b)  pour refaire le visuel mobile et rendre l'application fonctionnelle sur cellulaire.  
### Refonte visuelle première partie
Je démarre donc avec mon Issue #154, que j'ai découpé, ensuite, en 12 sub-Issue, c'est la base de ma refonte, mais je ne serais pas surpris que ça prenne de l'ampleur au fur et à mesure que je progresse. Première étape, déplacer le contenu de sidebar dans app.blade.php et suppression du sous-dossier layouts/app. C'est vraiment juste pour me simplifier la vie, car au final ça ne change rien, sinon que ça rend plus intuitif le code. Ensuite, j'ai replacé les descriptions de pages en tooltip sur le header, la convention heading -title - subtitle - description ne convient pas à l'usage de mon application. Au passage correction d'un "feature" inutilisé et ajout d'un component pour rendre les lignes des tables cliquables.

## Jour 6