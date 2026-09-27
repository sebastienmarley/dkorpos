---
title: "Choix et décision semaine 2"
date: 2026-09-26
draft: false
description: "."
categories:
  - "techno"
tags:
  - "décision technique"
  - "projet"
  - "réflexion"
---

Certains choix et décisions pris en cours de semaine

## BD
Peu de décisions de base de donnée cette semaine. Toujours en sqlite pour l'instant, je prépare à migrer vers MySQL la semaine prochaine, ça va me permettre d'utiliser TablePlus pour consulter et ou modifier la BD, ça va m'aider pour constater certain comportement, principalement les mouvements d'inventaires, les écritures d'inventaire unitaire et éventuellement l'aspect comptabilité qui est plus sensible si je développe ce côté.

## Interface
Comme vous avez pu le constater dans mes courts vidéos Youtube, je m'interroge régulièrement sur le rendu, l'interface et l'expérience utilisateur à travers l'application. Pour l'instant je suis concentré sur l'aspect "desktop" mais je vais devoir retravailler le côté mobile. Si certain menu se prête mal à un usage mobile, l'interrogation d'inventaire et la consultation d'horaire doivent assurément être optimisé pour celui-ci, il en ira de même avec le module de vente d'après moi. Je n'ai pas fini de travailler l'UI, mais j'ai choisi d'aligner la navigation en barre latérale à droite justement en pensant à un usage mobile. 

## Méthode horaire
J'ai longuement réfléchi à comment faire l'horaire et prendre les rendez-vous. Je ne crois pas que le model actuel soit satisfaisant. J'aimerais de quoi de plus dynamique, de plus je dois penser au validation et aux interactions, qu'arrive-t-il si 2 personnes modifie l'horaire en même temps, si 2 personnes veulent prendre un rendez-vous en simultané et qu'au final il y a chevauchement. 

## Sécurisation via les rôles
En faisant l'enum des rôles je me suis demandé comment je voulais légiférer les accès. Je crois que la simplicité serait d'y aller par rôle, cependant mon expérience en entreprise m' démontré qu'un employé à rarement un seul chapeau. Donner plusieurs rôles pourrait engendrer d'autres problèmes, comme des accès non désiré. Le concept de Gate de Laravel est intéressant, mais celui de permission me semble plus sur, surtout plus facile à manipuler pour rendre les accès vraiment spécifique à chaque employé. Donc en mettant un "template" de base selon le rôle on pourrait ensuite ajouter des permissions individuelles dans la gestion de l'usager. 