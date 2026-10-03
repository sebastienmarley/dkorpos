---
title: "Réflexion semaine 3"
date: 2026-09-29
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

## Jour 1
Aujourd'hui je recadre, je replace, j'établis mon plan de la semaine. J'ai mis à jour GitHub Projects. Ajoutés quelques pistes pour certains Issues. Je prends une heure pour peaufiner le module des usagés. [Voici un peu d'où je pars et ce que je désire](https://youtu.be/LziK_SDDkoE). Je deviens aussi beaucoup plus à l'aise avec la caméra, j'apprécie vraiment le médium.  
[Ça commence à prendre forme](https://youtu.be/LziK_SDDkoE).  L'IA a pris une tangente, mais de plus en plus j'arrive à avoir une certaine uniformité, le "Layout" n'est pas à mon goût, les couleurs non plus, mais pour moi ça c'est après la maquette, je touve même que je commence à être un peu loin dans mes démarches pour un canvas non approuvé, mais bon j'apprécie le moment de toute manière.  

## Jour 2
Court moment, j'ai fait les transcriptions des traces du dernier weekend et déposer sur gist.github   
Discussion intéressante avec mon collègue de bureau sur son expérience avec les dérapages de l'IA qui parfois réécrie ses règles ou se donnent des droits d'accès en SSH parce que la clé est dans le code et va ainsi modifier directement le code du "live". Son expérience m'est vraiment précieuse et à chaque jour j'apprends des CLI, des raccourcis et des paramètres à passer pour maximiser ce que j'utilise. Aujourd'hui, pint auquel je peux mettre --parallel pour faire les vérifications sur 4 coeurs au lieu d'un. C'est un exemple, mais honnêtement ce sont ces petites choses qui me font avancer dans mes apprentissages. Le revers du jour appartient à l'ENA c'est hors sujet, mais je vais devoir modifier un fichier pour le rendre compatible et lisible.

## Jour 3
[Transcriptions des discussions avec l'IA](https://gist.github.com/sebastienmarley/b5ac0c498d024798d977a65269b67f26.js).
Grosse soirée, [d'abord j'ai attaqué le système de permissions de spatie](https://youtu.be/S28BoXCs_LM).  
Pour permettre de gérer les accès, j'ai commencé à expliquer mes choix, mais j'irai plus en détail ce weekend. Ensuite je me suis attaqué à raffiner les fournisseurs, car je pense que ma prochaine étape serait de faire le module des commandes. Donc, pour se faire [j'ai modifié une partie](https://youtu.be/D4jM_Jid9Cc) des fournisseurs, ajouté le concept de devise afin de calculé le multiplicateur de vendant correctement. J'ai un souci à ce sujet, normalement c'est un pourcentage, exemple 35% de taux de change, 22% de frais de douanes, 8% transport, je peux l'écrire manuellement, mais j'aimerais que ce soit simple pour l'utilisateur, je dois réfléchir à la meilleure pratique, une conversion en mécanique arrière ou demandé à l'usager de faire ses maths et inscrire 0.08. Encore une fois je dois réfléchir aux champs obligatoires, tot ou tard ça va me rattraper. 

## Jour 4
Début de la branche commande fournisseur, il y a eu d'énorme progrès dans cette branche. J'ai pensé à 3 types de commande, en fait la 3e est plus une méthodologie pour générer des commandes. Il y a des commandes de fournisseurs de produits, des commandes de fournisseurs de services, c'est sur ces 2 là que je me suis attaqué car la base est la même, la différence est plus sur l'inventaire, puisque les services ne sont pas inventoriés de la même façon. Je vais devoir réfléchir sur comment je peux gérer cela. Ce qui me fait penser que je dois ajouter le concept de "commandable" à un fournisseur de service, car Hydro-Québec entrerait dans cette catégorie, pour pouvoir payer la facture, mais on ne commande rien chez Hydro. Le 3e type c'est pour commander selon les stats de vente, j'ai documenter l'idée dans mon GitHub Issue#28.  
En ce moment seulement la version pour les produits est avancée, je dois trouver un outil pour soumettre des courriels. Voir GitHub Issue#29.  
L'interface des commandes fournisseurs m'apparait très basique en ce moment, je dois réviser si tout fonctionne pour commencer, ensuite je passerai en peaufinage sur une prochaine itération, l'idée était de préparé le nécessaire pour l'intégration du module de vente.
Il faut que je termine le module de réception, le journal des mouvements et que j'ajuste des détails par rapport à l'inventaire FIFO, je dois intégrer la correction de réception aussi, en prévision de la facturation. Demain je dois m'attaquer aussi au test en situation d'utilisation.