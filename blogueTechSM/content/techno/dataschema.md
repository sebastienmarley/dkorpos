---
title: "Réflexion technique schéma de donnée"
date: 2026-10*18
draft: false
description: "."
categories:
  - "techno"
tags:
  - "technical research"
  - "projet"
  - "réflexion"
  - "database"
---

## Schéma

En faisant la construction du modèle de donnée je pensais simplement reprendre le "dump" du schéma SQLite et commenter la structure et les liens. En cherchant à avoir des exemples, l'IA m'a suggéré de passer par Mermaid pour rendre le tout plus graphique. Je regrette un peu le passage de l'IA qui a remaché mes commentaires initiaux, mais au final il y a eu tellement de passage d'IA par la suite que j'hésite à réécrire mes commentaires. Au final ça donne un résultat visuel correct, mais la vue d'ensemble est intense. Néanmoins, les autres représentations sont claires, les liens "belongs to" facile à voir, les clés primaires et étrangères bien identifiées, tout comme la structure des tables et les types des variables. Il suffit d'aller consulter : /docs/data-model.md pour voir de quoi je parle.

## Données

J'ai tenté d'organiser les données par module et mettre les tables en relation entre elle, j'espère ne pas avoir trop complexifié l'application en faisant cela ainsi. Surtout les permissions qui m'apparaissent comme un fouilli de tables pour en arriver à mes fins, n'est pas dit que je vais laisser ça comme ça d'ici le déploiement. Pour en revenir aux données, et leur relation, j'ai préféré une approche ou je pouvais minimiser les colonnes, mais maximiser l'information disponible. J'aurai peut-être besoin d'introduire des données "guarded" à certain endroit afin de mieux protégé les manipulations de celle-ci. Je me réserve une semaine pour repasser les "models" et réfléchir à comment protéger l'intégrité des données.  
Une chose dont je suis sur c'est que les types de données dans mes tables sont les bonnes. L'argent est en int qui utilise un cast, tout ce qui est toggle est un boolean, les informations en string, etc. Idem pour ce qui doit être "nullable" donc non nécessaire, voir même les contraintes de données. J'adore utiliser les enums, cela évite les erreurs de frappe, pareil pour les calendriers de sélection de date plutôt que se fier sur l'utilisateur et forcer un format avec x-data. J'ai quand même utiliser cette stratégie pour les téléphones, mais j'ai utilisé une méthode plus poussé pour les adresses en créant un gabarit d'adresse en blade. Ou sinon parfois pour permettre l'unicité d'un produit par exemple, j'ai du construire une fonction qui sauvegarde dans un champ "nettoyé" pour permettre des comparaisons, ou cette même contrainte d'unicité dans les UPC.

Sinon, en général on ne supprime rien, on archive, les données sont importantes, elles ne doivent pas disparaitre, souvent elles ne peuvent pas être altérer sans passer par des actions du système. De plus, lorsqu'une suppression est possible, un parent ne peut pas être supprimé s'il a encore des enfants, exemple une commande fournisseur ne peut pas être détruite tant qu'il y a une ligne de commande associée. Donc, il y a vraiment de nombreuses contraintes aux données, mais je n'ai pas mis trop de champs obligatoire, mon raisonnement est que faute d'être réellement dans la business il est très difficile de prendre ce genre de décision, normalement cela se ferait conjointement avec l'entreprise afin de livrer des tables avec des restrictions appropriées.  

Je suggère de consulter mon ADR-0006 pour d'autre détail sur la méthodologie prescrite en matière de base de donnée. Je conçois tout le projet en fonction d'un déploiement Laravel Cloud, donc une future migration en MySQL ou en PostGreSQL.