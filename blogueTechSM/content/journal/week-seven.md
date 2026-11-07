<<<<<<< Updated upstream
=======
---
title: "Réflexion semaine 7"
date: 2026-10-27
draft: false
description: "."
categories:
  - "Project"
tags:
  - "journal"
  - "projet"
  - "réflexion"
---

# Jour 5 : Le visuel et le mode mobile
Semaine de l'Halloween oblige, moins de temps pour le projet, mais bon ce matin je me lance, UI, je reformate complètement l'interface utilisateur. La navigation en mobile était absolument atroce, my bad je n'avais pas encore vraiment testé avec l'option de dév. dans Safari. Post deploiement j'ai testé sur mon cellulaire et j'ai eu un petit choc, je croyais pourtant avoir mis les restrictions en place au début, mais bon un petit refacto, la beauté d'utiliser des gabarits (blade et layout) c'est que ça s'adapte très rapidement et les modifications n'ont pas à être appliqué de long en large sur chaque page. J'ai ajouté une recherche de type "Spotlight" j'adore cette fonction dans l'application développée au travail, alors je savais que tôt ou tard je l'intégrerais dans celle-ci parce que ça permet d'accélérer le travail pour un commis de bureau. En plus il est possible de passer des fonctions et des arguments, alors un usager pourrait chercher commande fournisseur 8 et ouvrir la dite commande, etc. J'ai failli faire une gaffe et faire un merge de mon PR directement dans le main au lieu du develop, je dois vraiment faire plus attention. D'ailleurs, le PR a échoué les tests passaient en local, mais pas en git, à cause de la façon de passer la liste pour les titres de la nav.  
Nouveau bogue, ma page de lockout se déclenche même si travaille et elle n'accepte pas que j'entre mon NIP, le curseur doit être figé dans le modal en arrière plan et ne me permet pas d'entrer mon NIP. J'ai corrigé le problème, dû à Livewire finalement qui empêchait de rafraichir le token de session dans certaines instances amplifié dans mon environnement de développement qui se verrouille après une minute au lieu de 5.  
Ça y est, la fatigue a eu raison de moi, j'ai fait mon PR sans faire mon composer test et, bien sûr, une douzaine d'erreur sont apparus. Heureusement elles sont toutes similaires et du à mes derniers changement dans rendez-vous. Ce qui m'amène vers ma prochaine modification, le retrait du code mort, clairement j'ai des fonctions, des variables et des relations qui ne sont pas utilisé, idem j'ai assurément des reliques de gabarits qui appartiennent au kit de départ de Laravel-Livewire qui mériterait d'être retiré. C'est probablement 4-500 lignes de codes, mais bon tant qu'à être à cette étape aussi bien me débarasser du bois mort.  

# Jour 6 : La comptabilité
Journée consacrée à la comptabilité, les grands livres, les actions systèmes ont été ajustées pour tenir compte de ceux-ci. Donc un achat, une vente, un paiement de facture occasionne tous une écriture GL. Les taxes ont leur GL, la conciliation bancaire est intégrée et la valeur de l'inventaire est maintenant ajusté pour tenir compte du coût de transport, douanes et taux de change, bref un coût réel, au lieu de simplement un coût en USD. J'ai rédigé mon User Story en prose, quand même pas mal de texte autour de 400 mots. Texte que j'ai repassé à l'IA pour rebatir selon le format de mon gabarit. Cela me permet de détailler les composantes des ADR et m'assurer de construire un module avec lequel je suis plus ou moins à l'aise. L'Issue #131 et #132 sont donc nés, menant à la branche #133. Toutefois, ce faisant j'ai bien réalisé que ce serait beaucoup plus complexe que ce que je voulais bien. Refonte de tous les modules qui touchent à l'inventaire et à l'argent, ajout de plusieurs concepts, surtout que tout fonctionnait déjà correctement, je n'étais pas très chaud à l'idée de me lancer dans ce projet.  

Drôle de moment, pendant mes tests, j'ai voulu vérifier la paie, surprise ! Les commissions et primes ne sont que des placeholder fixé à 0$. Quick fix, retrait du commentaire et ajout des branchements pour que ça se calcule. J'ai peur qu'il y est d'autre bout de code comme ça. Je me note de vérifier le tout la semaine prochaine.


# Bon coup du jour
J'ai fait fabriquer mon demoSeeder, en utilisant les factory et les fonctions réelles de l'application et non en injectant des données dans la BD. L'avantage c'est que ça me permet de travailler avec du concret, tester certains comportement comme la paie, les paiements, les GL, etc. qui seraient ingérable normalement. Là un migrate:fresh --seed suivi d'un class:DemoDataSeeder et le tour est joué. Ce n'était pas sorcier et clairement j'aurais du y penser bien avant, mais bon peut-être aussi qu'en développement cela aurait trop demandé d'ajustement puisque j'utilise les fonctions de l'application. J'ai trouver des micro bogues ce faisant, mais rien pour en faire toute une histoire, une simple erreur d'écriture comptable dans commande client et un oubli dans le factory de produit.  

# Conclusion du weekend
Malgré le peu de temps, j'ai pu accomplir de nombreuses tâches qui m'énervaient. Suite au déploiement de la semaine dernière je me devais de déployer une mise à niveau pour permettre de fonctionner sur mobile, c'est chose faite. La migration de ce soir me fait plus peur, car elle implique ma première migration de table en prod. mais aussi il s'agit d'un gigantesque revamp de l'interface usager, si en desktop c'est peu visible il en va autrement sur téléphone. Tout semble fonctionner, le crochet fait son travail, les règles de déploiement aussi, les nouvelles permissions ont suivi et mes données de prod sont intactes. 
>>>>>>> Stashed changes
