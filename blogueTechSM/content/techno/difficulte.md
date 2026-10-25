---
title: "Difficultés rencontrées"
date: 2026-10-24
draft: true
description: "."
categories:
  - "techno"
tags:
  - "bogue"
  - "projet"
  - "réflexion"
---

## Difficultées avec le projet

C'est ici, selon moi, ma plus grande difficulté, car si mon projet avance à grand pas, les écueils se font rares. Si parfois un pépin de déploiement survient en raison d'une faute de frappe ou un test CI échoue à cause d'une variable d'environnement ( ex. APP_LOCALE=en au lieu de fr) je n'ai pas eu besoin jusqu'à maintenant de me servir des techniques pour trouver un bogue ou un problème. Cela va surement surgir sous peu, un projet sans problème est peu probable, donc je tenterai de conserver une trace des défis rencontrés et de la façon dont je les ai surmontés. 

## Défi 1

Intégrer réellement la possibilité d'avoir plus d'un magasin. J'avais mis le concept, mais je n'avais rien développé de cette façon là. Sans déclaré la catastrophe apocalyptique, j'ai définitivement brisé de nombreuses logiques. J'ai d'abord réparé l'ADR-0001 pour les permissions, ce qui me semblait logique. Ensuite j'ai entamé la descente aux enfers, tester chaque module avec 2 usagés différents. L'Admin, qui a la permission stores.all et un directeur de succursale de l'autre magasin qui lui ne l'a pas. Premier constat, les horaires, aucun lien avec le magasin, alors tous les employés de tous les magasins sont sur le même horaire. Ce faisant, l'IA a halluciné des besoins et brisé mon ADR-0001 en documentant les modifications de l'horaire dedans. J'ai du lui faire faire marche arrière et mettre cette doc. dans un nouvel ADR-0013. Ensuite j'ai du descendre chaque module et vérifier que tout était conforme. Car, évidemment tout était croche, en plus de briser un grand nombre de tests CI. Le tout via l'Issue #105 la branche bien mal nommé permissions. Je ne crois pas être au bout de mes peines avec cet ajout périlleux, mais bon à voir pour la suite.  