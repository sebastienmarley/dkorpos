---
title: "Réflexion semaine 2"
date: 2026-09-23
draft: false
description: "."
categories:
  - "Project"
tags:
  - "journal"
  - "projet"
  - "réflexion"
---

Ce journal se veut une réflexion sur ma journée de travail, l'IA, les bons et moins bons coups

## Jour 2

Début de ma semaine deux, aujourd'hui c'est KanBan, cette première entrée de journal et l'élaboration de certains de mes choix techniques.
Si l'IA m'a impressionné à plusieurs égards lors de la semaine un, hier pendant ma enième tentative d'aligner Pages et Hugo et que mon texte a été charcuté, dépersonalisé et simplifié poru la seconde fois, j'ai vraiment réalisé l'importance de la "query" l'écriture de notre conversation avec l'IA qui dicte la précision du résultat. Discussion avec mon collègue à nouveau, encore plein d'apprentissages sur une structure solide pour Laravel, je vais tenter de mettre ne pratique ses conseils.

## Résultats du jour
- Création du projet via la fonction Laravel new. 
- Épuration de certain template de base qui accompagne un nouveau projet Laravel.
- Création des tests CI de bases et du worflow github pour ceux-ci.
- Rerendu d'une page d'authentification de base.
- Début de la page d'accueil une fois connecté à l'app. 
- Travail sur la sidenav et le header.

## Réflexion du jour
J'ai installé ollama plutôt que de bruler des tokens avec mes 10000 tests. J'ai apprécié mon expérience, beaucoup moins éparpillé, je trouve les explications plus clairs et j'ai moins l'impression que l'outil fait tout pour moi partout. Soit c'est moi qui s'adapte en vitesse grand V, soit le modèle Gwen3.6 est mieux que celui proposé par VScode de base, je ne sais pas. J'ai aussi eu droit à un beau casse-tête avec la construction du projet, mais j'espère avoir résolu mes problèmes sans me faire de conflit.

## Jour 3
courte journée de travail, début de la construction de la structure de user dans Models/User. Entrée de journal et lecture de documentation Laravel.

## Jour 4
- Kanban, préparation pour le weekend
- Approfondissement de user 
  - ajout de la modal de création
  - modification du login
  - règle de création unicité username/email
  - index de la view/users avec une vue en table. à retravailler
  - 
- finir de transcrire le vieux Kanban réaliser en semaine 1.

## Réflexion du jour
Beaucoup plus dans le détail aujourd'hui, j'ai réfléchit à certaine structure, influencé en partie par mes lectures du module 3. J'essai quand même de ne pas trop tout prévoir, car je ne sais pas encore jusqu'où je développerai le projet.  
Je tente tout de même d'avoir un produit intéressant à présenter, et même si l'IA me suggère beaucoup de code, j'ai remarqué que je devais toujours peaufiné chaque petit détail. Exemple du jour, lors de la conception de l'index de user, tout était aligné à droite pour ressembler au fait la sidenav est à droite, avec en plus une inversion des éléments, ainsi le nom de l'usager se retrouvait à droite complètement. Finalement après un long échange avec l'IA qui m'obstinait dur comme fer que je devais seulement faire un clear de mes caches et de ma vue, j'avais raison, le vrai problème était dans le code, une fonctionalité de flux dlr="rtl" gachait l'ordre parce qu'elle ordonnait tout par la droite. Elle avait été activé dans le tout début lorsque j'avais demandé d'avoir ma nav à droite c'est ce qui m'avait été suggéré.

