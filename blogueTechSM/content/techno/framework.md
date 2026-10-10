---
title: "Réflexion technique Framework"
date: 2026-09-23
draft: false
description: "."
categories:
  - "techno"
tags:
  - "technical research"
  - "projet"
  - "réflexion"
---

Mes réflexions sur mes choix techniques.

## Framework

J'ai choisi d'utiliser Laravel, combiné à Livewire et Flux, AlpineJS, Herd et l'hébergement dans le nuage via Laravel Cloud, je pense avoir à la fois un framework puissant, capable d'offrir au client une solution robuste et sécuritaire tout en ayant une intégration dans le nuage au fort potentiel. Mais j'y reviendrai dans mon post sur le sujet. Laravel offre aussi une documentation exhaustive et facile à consulter, évite d'avoir besoin d'aller chercher et configurer de multiple outils et combine les avantages de plusieurs langages orientés web.  
Ce choix n'est pas un hasard, je cherchais un langage et un environnement dans lequel j'étais confortable d'évoluer. Je trouve le code lisible et j'apprécie la possibilité de déployer sur le web et d'y avoir accès en tout temps et partout, spécialement avec l'usage de l'infonuagique. Cela ouvre la porte à une intégration complète de la boucle dans un seul et même endroit. Que ce soit pour le livreur qui veut enregistrer preuve et signature ou le vendeur qui a besoin de son horaire ou consulter ses stats de vente. Tout se trouve à un endroit, l'information est colligée en base donnée et accessible.
Les commandes intégrés dans Laravel tel que php artisan migrate ou php artisan make controller abcde voir même littéralement laravel new abcde contribue à établir une base solide pour généré une application prête à l'emploi. Grâce à Laravel Boost l'agent IA réutilise les mêmes commande pour fabriquer les vues et les models, Flux fourni des gabarits pour toute sorte de composante et évite d'avoir à concevoir du code maison pour gérer tout ça, exemple Flux:modal ou Flux:toast pour un message de succès. Même la localisation peut se gérer aisément soit avec des variables de langues ou un JSON prévue à cet effet. Chacun à ses pour et ses contre, je ne sais pas quel méthode je vais privilégier si je décide de faire une localisation.
