---
title: "Itération 5 - semaine 1"
date: 2026-11-23
draft: true
description: "Préparation jalon 4"
categories:
  - "Projet"
tags:
  - "PHP"
  - "Laravel"
  - "IA"
  - "développement"
---

## Résumé des 2 dernières semaines

La fin approche, dernière itération, je n'ai pas fait de résumé de la semaine dernière, c'était trop intégré avec cette semaine. Bref, les quinze derniers jours ont été consacrés à la refonte visuelle de l'application. En commençant par la navigation sur petit écran (-xs), la réduction des tables qui s'affichaient au rendu de la page et la correction des gabarits de base de l'application. Soit l'Issue #154, redécoupé en douze sous Issues pour mieux travaillé. J'ai commencé par une première branche plus sur le visuel et les gabarits, ça c'était la semaine dernière surtout. J'ai frappé deux trois petits écueils, mais j'ai surmonté le défi, réduit le poids de l'appli, épuré le visuel, amélioré l'interface humain-machin en reformatant la taille et l'emplacement des champs, des boutons et des filtres et en simplifiant la navigation en général. Cette semaine j'ai terminé le projet de visuel et j'ai attaqué de front le module des produits, un module "ancestral" datant du 27 septembre dernier, deux mois plus tard, il était donc temps de reconstruire ce dernier, l'application ayant grandement évolué depuis. Ce faisant, un dernier petit tour d'épuration, une amélioration du générateur de donnée de démonstration (DemoDataSeeder) et des correctifs pour mettre à niveau les comportements de Livewire. 

## Leçons de la semaine

### Merci l'IA

Quand on pense qu'une tâche est hors de notre portée, que c'est un défi trop important, l'IA peut très bien accomplir cette tâche de moine. La documentation, lire les archives, aller chercher et colliger un tas de données. Sans cet apport je ne crois pas que j'aurais pu remettre en ordre avec autant de détails tous les problèmes du projet. Une solide requête, bien dirigé et le tour est joué, c'est une à deux semaines de travail assidu, en l'espace de 15 minutes, je ne peux pas jurer que c'est exhaustif, mais avec tous les liens je vais pouvoir contrevérifier et peaufiner sans avoir à enquêter mes description de 400+ commits à travers les 170 # du git et les 10 semaines de blogues, sans compter les ADR et les transcriptions. C'est en faisant cet exercice que j'ai réalisé la valeur de mon journal de bord et de mon entêtement à écrire moi-même mes descriptions de commit. Je comprends qu'elles ne sont pas toujours à la hauteur, qu'il y a un certain manque de relation avec les Issues, etc. Néanmoins, en ne me fiant pas à l'IA pour celles-ci, j'ai pu conserver mon style, mes mots, ma personnalité à travers eux, et lorsque Claude a rendu ce résumé des problèmes, j'ai plus le sentiment de lire mon narratif plutôt qu'un texte insipide.  

### Vive les gabarits Blade et les ressources communes.  

La refonte visuelle me faisait un peu peur, je n'avais pas envi de passer des heures à dire à un agent de faire ci ou ça. Au final j'ai pu jouer dans mes layout, dans mon app.css et dans mes components un peu par moi-même, m'appuyant sur des réponses de l'IA lorsque le contexte s'y prêtait. Quand les ramifications augmentaient je pouvais rapidement comprendre tout ce qui était impacté et comme le frontend appelent des gabarits pour tout je n'avais pas à modifier chaque virgule de chaque module. Je voulais plus de marge dans les tables, 2 lignes de codes, toutes les tables sont corrigées. Je voulais amélioré mes onglets de navigation de certaines pages, mais je n'ai pas Flux Pro, qu'à cela en tienne, avec Claude on a reconstruit quelque chose qui part de Flux Free et qui me donne un comportement et un visuel plaisant, le tout dans un component prêt à employer à nouveau si je développe un autre module qui en a besoin. J'aime le concept de ses gabarits, comme des pièces de casse-tête qu'on peut prendre et reprendre partout dans notre application et qu'on peut ajuster une fois en sachant que notre modification se fera partout. Pas besoin de me demander ou diable ai-je mis ce type de code, même si l'explorateur de VSCode me permet de retrouver rapidement une ligne de code, un "find and replace" est parfois aventureux et dangereux en code. 

## Les blocages rencontrés

## Les progrès concrets

## La semaine à venir

Tester avec les données du seeder, corriger l'interface de l'application pour la peaufiner et enrichir le blog.