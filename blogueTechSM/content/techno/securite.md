---
title: "Choix et décision de sécurité"
date: 2026-10-01
draft: false
description: "."
categories:
  - "techno"
tags:
  - "décision technique"
  - "projet"
  - "réflexion"
---

## Login
Password simple pour le moment, mais le 2 facteurs sera implémenté avant le déploiement et je vais ajouter le passkey via spatie/laravel-passkey. Je force un mot de passe complexe à la création d'un nouvel utilisateur et il est impossible de le modifier autre qu'en demandant au directeur/propriétaire. Le concept est de rendre l'accès à l'application le plus restrictif possible, tout en évitant de s'embarrer.  

## Qui à accès à quoi
Chaque employé occupe une position dans la compagnie, mais la position ne dicte pas le rôle. Un vendeur peut agir comme 3e clé, mais ne pas faire de gestion, alors que la personne au service à la clientèle s'occupe d'une portion de la comptabilité. C'est pourquoi il faut une gestion des permissions agile qui fait fit du titre d'emploi et s'adapte à la réalité du terrain. C'est pourquoi je vois les rôles comme un regroupement des permissions de bases pour effectuer une sorte de tâche. Mais au final la plupart des employés ont des accès très limité et ensuite la personne en charge distribue les permissions en fonction des tâches de chacun. Petite contrainte, personne ne peut modifier sa propre fiche d'usager sauf le propriétaire et l'administrateur. 

## Qui voit quoi
Pour résoudre ce problème, je crois que cacher et barrer l'accès au module sans une permission est la meilleure solution. En utilisant le package spatie/laravel-permission je viens protéger les modules avec des accès pré-établi, mais je laisse la chance d'ajouter des permissions dans la gestion de l'usager, car les employés portent souvent plusieurs chapeau et si parfois une troisième clé n'est qu'un vendeur amélioré, d'autre fois c'est un atout qui fait de la comptabilité en partie ou même de la réception. Donc, ça permet d'attribuer des permissions rapidement un genre de groupe de permissions, mais ça laisse le loisir d'augmenter un rôle au besoin. J'ai intégré un bouton pour ajouter des permissions, l'idée et qu'une fois déployé, je ne sais pas si je peux facilement ajouter les permissions à la BD du "live". 