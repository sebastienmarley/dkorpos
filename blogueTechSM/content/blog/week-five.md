---
title: "Itération 2 - semaine 2"
date: 2026-10-18
draft: false
description: "Retour sur la dernière semaine de développement, les blocages rencontrés et les premiers constats."
categories:
  - "Projet"
tags:
  - "PHP"
  - "Laravel"
  - "IA"
  - "développement"
---

## Résumé de la semaine

Très peu de temps en soirée a été consacré au projet cette semaine, j'avais besoin d'un pas de recul, me ressourcer et retravailler les user story pour m'aligner avec les recommendations. J'ai donc raffiné mon github project, puis je me suis lancé dans les derniers modules de l'itération 2 afin de livrer un produit plus étoffé au client. Les livraisons et expéditions sont faites, l'inventaire annuelle, les ajustements d'inventaire et les mouvements ainsi que toutes les corrections que cela a engendrées. Le module d'impression d'étiquette pour les démonstrateurs, qui me donne envie de faire un gabarit pour les produits réservés des commandes clients. Ainsi que le développement des ADR liés à ces modules. 

## Leçons de la semaine

Prendre le temps d'écrire ma vision en prose, ensuite je peux donner à l'IA le gabarit et ma prose pour faire retranscrire le tout en un texte clair et compréhensif. Ce n'est pas mon jeu favori, mais je dois avouer que l'agent me souligne parfois des portions oubliées, comme par exemple le cas d'une annulation de produits pour livraison, rien n'était fait pour retirer la livraison. C'est souvent des détails comme ça, mais qui au final rendent le produit fonctionnel ou non. Je m'aperçois aussi que j'ai beaucoup plus de plaisir à approfondir les modules, les complexifier et en faire une solution plus adaptée. J'aurais peut-être gagné en efficacité si j'avais pris le temps de pousser les ADR j'aurais surement eu moins de va et viens dans le code et j'ai peur d'avoir du code mort en ce moment.

## Les blocages rencontrés

La comptabilité, quoi faire avec les grands livres, comment ça marche, de quoi ai-je besoin pour être fonctionnel, je doute fortement que l'IA m'offre le nécessaire dès le départ. En même temps je ne sais pas si je veux m'embarquer là-dedans. À cela s'ajoute mon questionnement face au concept de multi magasin, la façon de gérer l'inventaire et finalement le module de livraison que j'ai repoussé par trois fois. Si je n'ai pas régler encore les 2 premiers points, j'ai décidé de travailler sur les 2 derniers. La gestion de l'inventaire à été résolu par ADR, cela a vraiment aidé à mettre en place une structure pour l'ensemble de l'application, avec des components réutilisable qui forcent les transactions via un modèle précis. Le module de livraison lui m'a demandé de la réflexion, je voulais reprendre le code des rendez-vous, mais en même temps il y avait des contraintes de "place" de distance, de possibilité. J'ai choisi de faire une gestion des camions et des livraisons max par demi-journées par camion. C'est imparfait, dans un monde idéal j'aurais aimé un calculateur de distance et de route, mais cela demanderait une dépendance externe vers une autre application, en plus il faudrait considéré le volume cubique des produits vs le volume disponible du camion avec "l'ordre", je pense que j'essaie de rendre le concept trop "parfait" et trop près de la réalité. 

## Les progrès concrets

J'ai complété les derniers modules requis pour remplir 100% des fonctions de bases. Ce qui veut dire, livraison, étiquette, mouvement d'inventaire, expédition, grand livre et prise d'inventaire annuelle. On peut voir un survol [ici](https://youtu.be/4ieZpqr_tsc).  
[L'IA](https://youtu.be/-KeWhp_ZNFU) me permet de vraiment de réfléchir à ce que je veux. Et même si ma vision de départ est très loin du résultat initial que l'IA m'offre, je sais que je peux retourner modifier la présentation, corriger l'approche et obtenir les fonctionnalités recherchées. Exemple, le travail fait sur l'horaire cette semaine a permis de prendre une version 1 fonctionnelle et l'amené vers une v2 plus proche de l'outi que je désire offrir. En y ajoutant les notifications, la prise en considération des rendez-vous, la gestion rapide des rdv à changer, etc. 

- Hors champs, mais j'ai fait la prise d'inventaire annuelle, pour le plaisir de la chose.
- Concept d'utilisation d'un lecteur optique lors de la prise d'inventaire
- Module de livraison de base
- Grand livre de base
- Raccordement des rendez-vous à l'horaire.

Bref une autre bonne semaine de progrès.

## La semaine à venir

En cette fin de 2e itération je considère que le produit est pratiquement utilisable tel quel. La prochaine itération portera sur les dernières fonctionnalités, soit la communication interne/externe, et sur le raffinement des fonctions développées à la première itération. Il ne reste qu'à pousser le module de livraison en ajoutant le paiement à la livraison, la signature client et les notifications. Tout cela à venir d'ici le 25 octobre. Remise du jalon 2 et prise de rendez-vous dès que possible.
