---
title: "Réflexion semaine 5"
date: 2026-10-13
draft: false
description: "."
categories:
  - "Project"
tags:
  - "journal"
  - "projet"
  - "réflexion"
---

# Jour 3
Après quatre jours de distanciation du code, j'ai surtout retravaillé mes Issues. Sinon, ce soir j'ai concentré mes efforts sur la modification de l'horaire pour empêcher les modifications simultanées. Refonte des modèles d'horaire type. Puis j'ai aussi intégrer la planification des rendez-vous futur dans l'horaire. Avec des avertissements clair pour le gestionnaire et un outil pour réassigner les rendez-vous vers d'auytres employés au besoin. Voici le gist pour les transcriptions de [conversation IA](https://gist.github.com/sebastienmarley/65717d75a1d4322db1af438e39225263.js).  

# Jour 5
Soirée de code, j'ai mené de front 2 projets. D'abord j'ai fait une refonte des sous-modules de couleur, département et catégorie pour en faire un seul avec des onglets. J'ai aussi retiré la dépendance bizarre entre catégorie et département pour plutôt faire des départements qui ont des catégories. Ajout de la notion de style au passage. Ce qui a forcé une restructuration du schéma de donnée, en plus d'avoir eu à y ajouter 5 nouvelles migrations en raison de l'autre module que j'ai conçu. En effet, 2e portion de la soirée consacré aux étiquettes de prix, soit un gabarit à appeler partout, un bouton qui déclenche un modal universel pour imprimer ou ajouter à une liste d'impression. Mais aussi, une page d'impression de cette liste avec option de nettoyer une fois terminé. Une table pour visualisé les étiquettes et en retirer au besoin. Puis un concept de cloche de notification, qui est déjà une partie intégrante de Laravel, je n'ai eu qu'à implémenter, mais il me sera utile pour les rendez-vous et d'autres modules. Finalement j'ai pu brancher tous les endroits qui devaient déclencher un changement de prix, la consigne étant "si en démo" sinon à quoi bon générer une étiquette.  

Le projet prend forme, j'ai ajouter une autre ADR, cette fois pour les étiquettes et la méthodologie de génèse de celles-ci. Je prépare en même temps le déploiement sur Laravel CLoud, il ne reste que 2 ou 3 fonctionnalités à créer et à intégrer. C'est assez incroyable de voir la différence en un petit mois, je suis passé de comment puis-je me servir de l'IA à comment l'IA peut m'aider à peaufiner mon idée et la transformer en code fonctionnel. J'ai hâte d'avoir terminé les dernières fonctions et me mettre à éplucher le code, je suis certain que les modules de départ auront besoin d'un grand ménage, déjà j'ai pu grandement amélioré les horaires types, la classification des produits, ajouter les notifications et redessiner la conception de l'horaire hebdomadaire en seulement 3 soirées.  

# Jour 6

Énorme journée de travail, mais mieux résumé dans l'entrée de blogue de la semaine. Beaucoup de travail abattu, le produit est presque 100% livrable, Cette semaine je terminerai mes lectures et je me renseignerai sur ce qui pourrait régler mes problèmes de communication avec l'extérieur.

# Jour 7

Très court moment, hier en faisant mon déploiement de journal j'ai eu un échec de build. J'ai eu mon courriel d'avertissement juste avant de me coucher alors j'ai du patienter jusqu'à ce soir. En allant consulter dans l'erreur dans le git j'ai vu qu'il s'agissait d'une date. J'avais fait une faute de frappe en mettant une * au lieu d'un -, et je la fais souvent sur le clavier Mac. Sinon, j'ai pris le temps de lire sur Laravel Tinker, je ne sais pas si je vais m'en servir pendant ce projet, mais ça me semble un outil intéressant pour tester des trucs ou utiliser les factory, etc.