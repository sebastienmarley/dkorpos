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

## Jour 5
Journée bien remplie.  
- Gestion des commandes fournisseurs
- Réception des commandes fournisseurs
- Facturation des commandes fournisseurs
- Modification des permissions
- [Gestion des magasins](https://youtu.be/rTA42DkVWno).  

Que d'écueils aujourd'hui, 2 branches complétées et fusionnées, mais non sans embûches. En modifiant les accès et les permissions je me suis rendu compte que les tests CI sont erronés à plusieurs endroits. Le UserFactory utilise salesman comme rôle par défaut, or ce dernier a vu ses permissions fondre dans ma refonte, cela a causé des centaines de tests à échouer. Tout comme une permission que j'ai ajouté, mais oublié d'intégrer au seeder, alors le test de Factory cherchait quelque chose qu'il ne créait pas. J'ai déjà 1644 assertions dans 568 tests et j'ai l'impression que plusieurs sont inutiles ou désuet. Je vais devoir penser à corriger ces tests d'ici le nouveau sprint qui débute dans 3 jours, question d'avoir quelque chose de propre pour débuter la pahse d'épuration et amélioration. On dirait qu'à chaque session je ne fais que grossir un monstre et même si l'application prend forme j'ai peur de n'avoir qu'effleuré ce que je dois implémenté pour que tout fonctionne adéquatement. [Néanmoins quelques concepts](https://youtu.be/hbgUB4C2Wqw) sont abordés ici, l'importance de l'enum, les fonctions comme service, etc.  
[Les commandes fournisseurs prennent forme](https://youtu.be/rTA42DkVWno), j'adore le module de [facturation](https://youtu.be/MyPFLtbz4BY), c'est simple épuré et très proche de la forme finale que j'envisage. Par contre les commandes elles, ouf! J'ai du travail de raffinement visuel, mais aussi structurel, la recherche n'est pas au point, l'ajout de produit ne me satisfait pas, j'aimerais peut-être inclure le service de création de produit. L'interface est difficile de lecture. Je n'aime pas non plus l'index des commandes, je crois que je vais devoir séparé les "vues" par le statut des commandes, aussi je dois changer le libellé de brouillon à ouverte.  
Le système de réception des commandes est une horreur sans bon sens. Ce n'est pas du tout fonctionnel, je dois le simplifier, penser à la réception à l'aveugle ? Cependant c'est plus pratique à la "facture", une réception contient plusieurs bon de commande différent d'un même fournisseur et la facture comprendra l'ensemble des bons de commande expédiés. Si on y va à l'aveugle ce sera un bon de commande à la fois, et ça implique de balayer des codes barres (UPC, EAN, etc.), mais ce n'est pas tous les produits qui en ont un, donc on a aussi une impasse. Est-ce que j'ajoute de pouvoir balayer les UPC pour faire la réception ? Si oui par incrément de 1 ou c'est balayer puis indiquer une quantité ? Il faut aussi que je pense aux interactions avec le module des ventes, les clients à mettre de côté, les expéditions, les livraisons à déclenché. Bref une refonte il faudra, mais avant toute chose un système de vente.  
En faisant [mes modifications de permissions](https://youtu.be/GDNw2B7RyhU) j'ai brisé les tests CI, cela m'a fait réaliser que tous les tests CI depuis l'introduction des permissions fonctionnaient mais "faussement" puisque l'utilisateur salesman avait accès à tout. Je vais devoir terminer l'attribution des permissions et réécrire les tests qui impliquent celles-ci. 