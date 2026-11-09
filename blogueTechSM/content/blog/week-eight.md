---
title: "Itération 4 - semaine 1"
date: 2026-11-08
draft: false
description: "Dépot du jalon 3"
categories:
  - "Projet"
tags:
  - "PHP"
  - "Laravel"
  - "IA"
  - "développement"
---

## Résumé de la semaine

Refonte visuel du blog et construction du système de communication de l'application. Courte semaine de travail, j'avais moins d'énergie et de temps à y consacrer. L'appli est déployée, tout fonctionne, je suis prêt à la déposer et la présenter pour analyse. [Courte présentation](https://youtu.be/EZOONXPVbpA) pour fêter ça.  

## Depuis la rencontre

### Comment ça va ?

L'application est passée d'un environ local à un déploiement complet et [public](https://dkor-prod.laravel.cloud) protégé par une combinaison usager/mot de passe, 2FA et NIP de session. Elle est aussi adapté aux écrans de téléphones et de tablettes, avec des comportements adaptés à la taille de ces écrans. La quasi totalité des fonctions de l'application sont aussi présente, il ne manque que les services externes auquel je ne suis pas encore abonné, cela ira pour la livraison finale dans 2 semaines. Il ne reste que mon Issue lié à Nightwatch et le #40 avec lequel je tergiverse toujours. J'ai commencé à noter mes [défis]({{< relref "techno/difficulte.md" >}}), peut-être qu'avec mes tests de données j'aurai des situations plus problématique que je pourrai noter en détail.  
Sinon, je considère que le projet s'est déroulé rondement, définitivement l'apport de l'IA a complètement modifié le portrait. J'ai pu me lancer dans des modules très complexe que je n'aurais pas oser toucher sans. En plus, comme j'ai changé de modèle en cours de route, je vois une énorme différence sur les réponses, les rétroactions sont plus profondes, Claude ne fait pas qu'exécuter une commande, je reçois des explications sur la façon de coder, sur le fonctionnement, le comportement, j'apprends même pourquoi je dois faire quelque chose d'une façon plutôt que d'une autre afin de respecter les conventions.  

### Ce qui va moins bien

Dans les trucs plus complexe et difficile, le UI m'a exigé un bon effort de remodelage, comme je l'ai décrit dans le journal des défis, je croyais avoir fait le nécessaire au début, mais je me suis rendu compte une fois en production que le résultat était un échec total. J'ai découvert au passage [la fonction de développement de Safari](https://youtu.be/EZOONXPVbpA) pour mettre en vue iphone/ipad. Alors j'ai pu tester adéquatement en local et corriger, je crois, la plupart des interfaces et menus qui seraient employés sur l'un ou l'autre des supports. Ainsi grâce à une tablette et une connexion cellulaire un magasin pourrait poursuivre ses transactions en cas de panne de courant. Néanmoins, je considère encore que mon UI est le talon d'Achille de l'application, les menus sont souvent grossiers ou surchargés, j'ai commencé à faire le ménage, mais je vois que mon empressement initial à vouloir produire l'appli la plus complète possible me rattrape. La présentation par table rend la lecture de certains menus (commande fournisseur/client) difficile, l'horaire et les plages horaires ne sont pas super intuitif et la comptabilité me laisse perplexe. Bref, les 3 dernières semaines du projet devront être consacrés aux tests et à l'amélioration du UI, je désire vraiment un résultat où je suis en mesure de me dire, ah oui j'aimerais que ce soit mon outil de travail. 

### Et si je recommençais ?
Si j'avais a faire un Laravel new, je crois que je débuterais mon projet en écrivant des ADRs solides, en préparant un gabarit de User Story plus facile à remplir et en créant des règles de codification pour l'IA. Les règles de Laravel Boost sont intéressantes d'un point de vue de façon d'écrire et de créer en Laravel, mais il m'a longtemps manqué des directives pour orienter le projet. Ensuite, j'ajouterais des règles pour les tests afin de ne pas en créer des superflus qui vont finir par ne rien tester de concret. Je débuterais le projet directement en MySQL ou PostgreSQL plutôt que de passer de SQLite à mon choix, j'intégrerais aussi tout de suite le workflow de déploiement, branche -> develop -> main avec "hotfix" -> les 2. De plus, je pense que l'ordre des modules seraient différent, j'ai intégré la comptabilité sur le tard, cela à forcé énormément de migration, j'ai l'impression d'avoir perdu le contrôle de mes modèles et de mes tables relationnelles, j'ai littéralement une table de relation pour des retraits de permissions, associées à une table de rôle qui donne des permissions, si en surface c'est transparent pour le client, en arrière c'est inutilement complexe pour le programmeur. Je construirais aussi mon "seeder" dès le départ, de nombreux test avec données m'ont révélés des écarts de comportement ou des problèmes de visuel/de UI, j'aurais abordé ceux-ci plus rapidement. Enfin, parlant d'UI, j'aurais aimé mieux construire mon "BaseLayout" dès le départ, maintenant j'ai peur de le modifier et de briser la présentation partout dans l'appli. J'aurais probablement découpé mes blade différement pour permettre d'avoir un UI plus adapté aux besoins de la vue active. 


## La semaine à venir

Tester avec les données du seeder, corriger l'interface de l'application pour la peaufiner et enrichir le blog.