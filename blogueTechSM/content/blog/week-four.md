---
title: "Itération 2 - semaine 1"
date: 2026-10-11
draft: false
description: "Retour sur la première semaine post jalon 1, les blocages rencontrés et les constats."
categories:
  - "Projet"
tags:
  - "PHP"
  - "Laravel"
  - "IA"
  - "refonte"
---

## Résumé de la semaine

Issue #13 voilà ! J'ai investi la semaine complète sur ce module et ce qui l'entoure. La commande du client, la commande chez le fournisseur, la réception des marchandises et la remise au client. En plus d'avoir refait le système pour compter l'argent, composer des ADR en prose, réécrire mon GitHub pour que ça ressemble plus à des User Story et j'utiliserai ce gabarit à l'avenir pour mes Issue supplémentaire, si le contexte s'y prête. Je relie beaucoup plus les commit et les Issue aussi, je fais des branches plus systématiquement et j'adopte une méthodologie plus facilement traçable, le tout sans utiliser l'IA pour ça, car je sais que ce pourrait être facile de lui faire écrire mes story, mes commits et mes Pull Request avec plein de détails techniques qui m'échappent, mais se serait me mentir quant à ma capacité à les composer et je ne suis pas à l'aise avec ça. J'ai fait le test avec l'Issue #41 pour le plaisir de la chose, et c'est stupéfiant, cependant certaine décision métier sont plus ou moins exacte et ont dû être reprise en cours de route. Ce fut somme toute une semaine très chargée, j'ai abattu énormément de travail et je suis pas mal certain de livrer un concept 100% fonctionnel la semaine prochaine. Mon objectif est d'ensuite prendre 4 semaines pour remodeler le projet et l'appronfondir en complexifiant chaque module afin de les rendre le plus complet possible au lieu d'être simplement fonctionnel.

## Leçons de la semaine

L'IA peut répondre à la plupart de mes interrogations et contribuer à améliorer mon processus créatif en m'aidant à jeter les balises et les règles liées à celui-ci. Avoir compris l'utilité d'avoir des Issue détaillés et des ADR pour les décisions techniques j'aurais probablement finis tous mes modules. Mais bon, maintenant je veux conserver ces bonnes pratiques et pousser le code à être plus raffiné.

## Les blocages rencontrés

C'est quoi un User Story, qu'est-ce qui le rend efficace ? Idem pour l'ADR. J'ai cherché un peu, j'ai observé des Issue de d'autre personne, comme sur le git de Laravel par exemple, juste pour m'inspirer, j'ai compris que je gagnerais à avoir un gabarit et c'est là qu'est intervenu l'IA. Est-ce que le gabarit proposé est idéal ? Non, je le trouve à la limite fastidieux, voir même "old school".  
J'ai aussi eu à retravailler certains aspect du code, la refonte de la façon dont je traite l'argent, sans être un blocage, aurait fini par en devenir un. J'ai évité de me casser la tête sur un bogue "fantôme" qui se produit peut-être une fois sur mille calculs.  
Un autre mini blocage a été expérimenté avec la vente de service, je pensais inclure les produits sur-mesure avec ceux-ci pour ne pas me casser la tête, mais au final ça ne fonctionnait pas à mon goût. J'ai opté pour une autre option, que je vais développer au courant de la prochaine semaine.

## Les progrès concrets

- Système de vente
 - Ajout de produit
 - Ajout de service
 - produit sur mesure (en cours de dév.)
 - Ajout du client
 - Ajout du ou des vendeurs, pour lien avec les futures stats(à dév.)
 - commande automatique chez le fournisseur
 - Attribution des stocks auto et manuel
 - Balayage UPC auto-focus pour imiter une caisse
 - Retour de marchandise
 - Paiement et remboursement
 - Crédit magasin gérer via le "compte" client
 - Statut de commande et de ligne de commande
 - Table de BD 
- ADR
- Gabarit varié pour GitHub et pour mes futurs ADR
- Construction d'un schéma de données via Mermaid, ce n'était pas mon but premier, mais je trouvais le résultat amusant.
- Refonte de la BD pour l'argent passant de float à int via [l'usage d'un cast](https://gist.github.com/sebastienmarley/2be040591c3194055864cc68e60471de.js) le lien pour le gist, plus détaillé cette fois.  
- [Les erreurs de réception](https://gist.github.com/sebastienmarley/40c03cb8c654d4bf24a14a4dd703c2b1.js) avec son gist. 

## La semaine à venir

Semaine plus calme à venir, je m'attends à faire le système de livraison et peaufiner un peu plus le système de vente. Dépot de mon jalon 2 à venir aussi.