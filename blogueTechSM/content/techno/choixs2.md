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

## Pourquoi Laravel et Livewire
D'abord et avant tout pour la simplicité. J'ai considéré React et Vue, mais même si je n'ai jamais vraiment écrit de code pour le web, j'ai eu à lire et travailler avec cet environnement, mon choix en est un d'accessibilité. En plus avec Livewire j'évite des requêtes au serveur et j'obtiens une fluidité visuelle qui m'est familière. Je vais devoir ajouter des couches de sécurité pour éviter de l'injection de code, empêcher la fabrication de donnée (forge data) et aligner la vue avec le serveur, je vais surement avoir des casses-têtes avec les mises à jour simultanées, mais j'ai confiance d'y arriver.  
L'autre aspect c'est de pouvoir faire mes validations en frontend et en backend, les authorisations, etc. Je sais que je pourrais même écrire front et back dans le même fichier, mais pour éviter de me perdre je préfère séparer les deux.

## Pourquoi Laravel suite
Une autre raison est Laravel Cloud, qui me permettra de déployer l'application et de la faire vivre dans un environnement infonuagique vraiment performant, une mise à l'échelle de qualité et le tout pour un coût à l'usage très avantageux, combiné avec la disparition de la maintenance du serveurs, plus besoin de se soucier des migrations de version Linux, etc. pas de temps mort, le serveur et les "worker" se réveillent au moment ou l'usagé en a besoin, cela allonge de quelques secondes le premier "boot" mais cela évite aussi d'avoir un serveur qui attend. 