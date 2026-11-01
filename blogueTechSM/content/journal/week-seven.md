---
title: "Réflexion semaine 7"
date: 2026-10-27
draft: false
description: "."
categories:
  - "Project"
tags:
  - "journal"
  - "projet"
  - "réflexion"
---

# Jour 5
Semaine de l'Halloween oblige, moins de temps pour le projet, mais bon ce matin je me lance, UI, je reformate complètement l'interface utilisateur. La navigation en mobile était absolument atroce, my bad je n'avais pas encore vraiment testé avec l'option de dév. dans Safari. Post deploiement j'ai testé sur mon cellulaire et j'ai eu un petit choc, je croyais pourtant avoir mis les restrictions en place au début, mais bon un petit refacto, la beauté d'utiliser des gabarits (blade et layout) c'est que ça s'adapte très rapidement et les modifications n'ont pas à être appliqué de long en large sur chaque page. J'ai ajouté une recherche de type "Spotlight" j'adore cette fonction dans l'application développée au travail, alors je savais que tôt ou tard je l'intégrerais dans celle-ci parce que ça permet d'accélérer le travail pour un commis de bureau. En plus il est possible de passer des fonctions et des arguments, alors un usager pourrait chercher commande fournisseur 8 et ouvrir la dite commande, etc. J'ai failli faire une gaffe et faire un merge de mon PR directement dans le main au lieu du develop, je dois vraiment faire plus attention. D'ailleurs, le PR a échoué les tests passaient en local, mais pas en git, à cause de la façon de passer la liste pour les titres de la nav.  
Nouveau bogue, ma page de lockout se déclenche même si travaille et elle n'accepte pas que j'entre mon NIP, le curseur doit être figé dans le modal en arrière plan et ne me permet pas d'entrer mon NIP. J'ai corrigé le problème, dû à Livewire finalement qui empêchait de rafraichir le token de session dans certaines instances amplifié dans mon environnement de développement qui se verrouille après une minute au lieu de 5.  
Ça y est, la fatigue a eu raison de moi, j'ai fait mon PR sans faire mon composer test et bien sur une douzaine d'erreur sont apparus. Heureusement elles sont toutes similaires et du à mes derniers changement dans rendez-vous. Ce qui m'amène vers ma prochaine modification, le retrait du code mort, clairement j'ai des fonctions, des variables et des relations qui ne sont pas utilisé, idem j'ai assurément des reliques de gabarits qui appartiennent au kit de départ de Laravel-Livewire qui mériterait d'être retiré. C'est probablement 4-500 lignes de codes, mais bon tant qu'à être à cet étape aussi bien me débarasser du bois mort.