---
title: "Itération 3 - semaine 2"
date: 2026-11-02
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
Voici le [gist](https://gist.github.com/sebastienmarley/21737cd0ef718a95a9e6ecd9d0aaf281) de la semaine, le format n'est pas super découpé cette semaine, 100% ma faute. Je suis en mode rattrapage de mes pertes et oublis, c'était donc plus décousus, heureusement que je suis plus ordonné normalement, mais aussi que je fais mes transcriptions après chaque session et mes entrées de journaux quotidiennenement.

Semaine de l'Halloween oblige, moins de temps pour le projet. J'ai reformaté complètement l'interface utilisateur pour rendre l'application utilisable sur mobile, et ajouté une recherche de type « Spotlight ». J'ai ensuite consacré une journée à la comptabilité : grands livres, taxes, conciliation bancaire et coût réel de l'inventaire. J'ai aussi fait fabriquer un demoSeeder. Le tout a été déployé en production, avec ma première migration de table en prod.

## Leçons de la semaine

- Tester sur mobile plus tôt : je n'avais pas encore vraiment testé avec l'option de développement dans Safari, et j'ai eu un petit choc en ouvrant l'application sur mon cellulaire après le déploiement.
- La beauté d'utiliser des gabarits (Blade et layout) : ça s'adapte très rapidement et les modifications n'ont pas à être appliquées de long en large sur chaque page.
- Faire attention à la branche cible : j'ai failli fusionner mon PR directement dans le main au lieu du develop.
- Toujours lancer `composer test` avant un PR, même fatigué.
- Rédiger mon User Story en prose (environ 400 mots), puis le faire rebâtir par l'IA selon mon gabarit, me permet de détailler les composantes des ADR et de construire un module avec lequel je suis plus ou moins à l'aise.
- Un seeder qui utilise les factory et les fonctions réelles de l'application permet de travailler avec du concret. J'aurais dû y penser bien avant.

## Les blocages rencontrés

**La navigation mobile.** Elle était absolument atroce. Je croyais pourtant avoir mis les restrictions en place au début ; un petit refacto a réglé le problème.

**Les tests CI.** Mon PR a échoué : les tests passaient en local, mais pas sur GitHub, à cause de la façon de passer la liste pour les titres de la navigation. Plus tard, un PR fait sans `composer test` a fait apparaître une douzaine d'erreurs, toutes similaires et dues à mes derniers changements dans les rendez-vous.

**La page de verrouillage.** Elle se déclenchait même pendant que je travaillais et n'acceptait pas mon NIP : le curseur restait figé dans le modal en arrière-plan. La cause : Livewire empêchait de rafraîchir le jeton de session dans certaines instances, ce qui était amplifié dans mon environnement de développement qui se verrouille après une minute au lieu de 5.

**La comptabilité.** En rédigeant l'Issue #131 et #132, j'ai réalisé que ce serait beaucoup plus complexe que ce que je voulais : refonte de tous les modules qui touchent à l'inventaire et à l'argent, ajout de plusieurs concepts, alors que tout fonctionnait déjà correctement.

**La paie.** Pendant mes tests, j'ai découvert que les commissions et les primes n'étaient que des placeholders fixés à 0 $.

## Les progrès concrets

- Refonte complète de l'interface utilisateur et de la navigation mobile
- Recherche de type « Spotlight », qui accepte des fonctions et des arguments (ex. chercher « commande fournisseur 8 » et ouvrir la dite commande)
- Correction du bogue de la page de verrouillage
- Comptabilité (Issue #131 et #132, branche #133) :
  - une écriture GL pour chaque achat, vente et paiement de facture
  - un GL pour les taxes
  - la conciliation bancaire intégrée
  - la valeur de l'inventaire ajustée selon le coût de transport, les douanes et le taux de change, pour un coût réel au lieu d'un simple coût en USD
- Calcul des commissions et des primes dans la paie
- demoSeeder : un `migrate:fresh --seed` suivi d'un `class:DemoDataSeeder`. Il m'a permis de trouver des micro bogues : une erreur d'écriture comptable dans commande client et un oubli dans le factory de produit
- Déploiement de la mise à niveau mobile et première migration de table en production : le crochet fait son travail, les règles de déploiement aussi, les nouvelles permissions ont suivi et les données de prod sont intactes

## La semaine à venir

- Retirer le code mort : fonctions, variables et relations non utilisées, et reliques de gabarits du kit de départ Laravel-Livewire, probablement 4 à 500 lignes.
- Vérifier s'il reste d'autres bouts de code mort comme les placeholders de la paie.
- Refonte du blog
- Communication, impression, gabarits de courriel
- Notification aux clients
