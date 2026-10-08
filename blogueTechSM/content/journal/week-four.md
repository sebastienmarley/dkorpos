---
title: "Réflexion semaine 4"
date: 2026-10-06
draft: false
description: "."
categories:
  - "Project"
tags:
  - "journal"
  - "projet"
  - "réflexion"
---

Ce journal se veut une réflexion sur ma journée de travail, l'IA, les bons et moins bons coups. 

# Jour 1
J'ai pris connaissance de la rétroaction sur la première itération. Le "client" approuve la maquette et la continuité du projet. En contre partie je m'engage à restructurer le projet, à mieux détailler les GitHub Issue, à poursuivre la méthode de journal actuelle et à mener à terme l'ensemble des modules. C'est drôle mon collègue me mentionnait hier que je devais faire attention à ne pas m'éparpiller pour livrer un paquet de petits modules entamés et plutôt me concentrer à livrer le "core" aussi parfait que possible. Aujourd'hui j'ai débuté le chantier des ventes, en m'attardant surtout à documenter les Issues. J'ai commencé à écrire mes sub-issues aussi, mais je n'ai pas pu résister et j'ai parti la base. Mais, vraiment une fonction à la fois. Minimaliste, pour faire un commit clair par issue. Je vais travailler très fort pour étaler le plan avant de toucher au code et je vais faire de même avec l'ensemble du projet, quitte à retourner dans ce qui est terminé pour relier mes anciens commit. Donc bref, beaucoup de texte, pas beaucoup de code, mais surtout beaucoup d'enthousiasme à poursuivre. Je ne compte pas mes heures, c'est pratiquement du loisir en ce moment. Ce qui me fait penser que je dois déposer mes transcriptions de dimanche. Je n'ai pas tant touché à l'IA aujourd'hui sauf pour les tests, qui se sont avérés erronés au départ, mais bon. J'ai surtout copié collé et appelé du code déjà fait, ça fonctionne, mais est-ce optimal, ça reste à voir, ça ira à ce weekend, le reste de la semaine sera consacré au "revamp" de GitHub Project.

# Jour 2
Journée passé entre la progression du module de vente et la réfection de GitHub Project pour être mieux organisé et répondre aux exigences, du moins je l'espère. Petit oops que j'ai remarqué aujourd'hui, depuis hier je travaille sur l'index.blade des commandes clients, mais cela venait bloquer toute forme possible de créer 2 commandes en même temps. J'ai rectifié le tout et passé dans le "show" dès la création d'une commande (avec le bouton). Ou si on ouvre une commande pour modification, consultation. En me relisant d'hier, voici [mon gist pour la fin de la semaine 3 : ](https://gist.github.com/sebastienmarley/39f2f0a5f76f721d048d2a8607bcddf9.js).  
Mais aussi [celle d'hier et aujourd'hui](https://gist.github.com/sebastienmarley/e39b468a8d7f6da24cea631aedfa7a5c.js). J'ai tendance à oublier de posté, au moins j'ai pris l'habitude de faire la transcription et de la mettre dans un dossier gitignore, je n'ai pas à chercher et c'est facile de reprendre les données pour les déposer dans le gist par la suite. Pas de vidéos avant demain, mais j'ai hâte de reprendre, j'aime le médium et ça me permet de montrer l'avancement et de remarquer certain comportement inadéquat. Je trouve très difficile de ne pas me lancer dans le code à fond, faire toutes mes fonctions puis revenir et tester, c'est tout un exercice d'être méthodique et d'associer chaque fonction à un commit, détailler la fonction et m'assurer d'avoir l'Issue qui lui est associé. Mais bon, je vais y arriver.  
# Les progrès à date
J'ai réussi à faire une commande client, la mettre en commande chez le fournisseur, les concepts de retrait selon le statut fonctionne dans les 2 sens. La substitution dans la com. fournisseur fait aussi les étapes dans la commande client, idem pour l'annulation. La réception prend en charge les mouvements d'inventaire et le changement de statut de la ligne client. Il reste encore du travail à faire pour la portion réception partielle, annulation partielle, etc. En ce moment c'est traité mais trop superficiellement. Il faut aussi que j'ajoute les notifications vendeur et client (twilio?). 