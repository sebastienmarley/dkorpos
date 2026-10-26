---
title: "Difficultés rencontrées"
date: 2026-10-24
draft: true
description: "."
categories:
  - "techno"
tags:
  - "bogue"
  - "projet"
  - "réflexion"
---

## Difficultées avec le projet

C'est ici, selon moi, ma plus grande difficulté, car si mon projet avance à grand pas, les écueils se font rares. Si parfois un pépin de déploiement survient en raison d'une faute de frappe ou un test CI échoue à cause d'une variable d'environnement ( ex. APP_LOCALE=en au lieu de fr) je n'ai pas eu besoin jusqu'à maintenant de me servir des techniques pour trouver un bogue ou un problème. Cela va surement surgir sous peu, un projet sans problème est peu probable, donc je tenterai de conserver une trace des défis rencontrés et de la façon dont je les ai surmontés. 

## Défi 1

Intégrer réellement la possibilité d'avoir plus d'un magasin. J'avais mis le concept, mais je n'avais rien développé de cette façon là. Sans déclaré la catastrophe apocalyptique, j'ai définitivement brisé de nombreuses logiques. J'ai d'abord réparé l'ADR-0001 pour les permissions, ce qui me semblait logique. Ensuite j'ai entamé la descente aux enfers, tester chaque module avec 2 usagés différents. L'Admin, qui a la permission stores.all et un directeur de succursale de l'autre magasin qui lui ne l'a pas. Premier constat, les horaires, aucun lien avec le magasin, alors tous les employés de tous les magasins sont sur le même horaire. Ce faisant, l'IA a halluciné des besoins et brisé mon ADR-0001 en documentant les modifications de l'horaire dedans. J'ai du lui faire faire marche arrière et mettre cette doc. dans un nouvel ADR-0013. Ensuite j'ai du descendre chaque module et vérifier que tout était conforme. Car, évidemment tout était croche, en plus de briser un grand nombre de tests CI. Le tout via l'Issue #105 la branche bien mal nommé permissions. Je ne crois pas être au bout de mes peines avec cet ajout périlleux, mais bon à voir pour la suite.  

## Le passage en production

Je savais qu'il y aurait des petits défis, des questionnements, un peu de recherche, mais ce matin en me lançant à construire un User Story (Issue #107) et l'ADR-0014 qui l'accompagnent j'ai fait le saut. Il y a tellement de point à penser, ex. je savais qu'il y a une loi pour l'hébergement des données. Je dois donc m'assurer que le serveur d'hébergement des données est au Canada pour répondre aux exigences de la loi 25. C'est aussi l'occasion pour moi d'ajouter la notion de "develop" vs main dans mon github, mais ça a eu pour effet d'avoir à corriger des ADR et les workflows, j'ai peut-être évité un casse-tête ici en y passant d'avance. Quand je lis l'ensemble du chantier certaine portion me font un peu craindre le pire, le passage vers PostGreSQL entre autre, je sais que la quasi totalité de l'app a été conçu en fonction de cette possibilité, mais j'ai des réserves. Pour l'instant la base semble pas briser. 
# 2FA
Bon évidemment il fallait que j'écrive ça va bien pour que l'implémentation du 2FA casse. Au fond j'ai activé le 2FA de Fortify, au début all good, le système fonctionne et fait exactement ce que je veux, cependant la boucle amène dans la config de l'usager et on a accès au menu de navigation latérale. Pour régler le problème j'ai voulu isolé le réglage initiale du 2FA en lui faisant un layout personnalisé, ce faisant le bouton a cassé. Apprentissage du jour, les @if doivent se trouver à l'intérieur d'un div dans un layout partiel, du moins le layout ne peut pas commencer avec un if, sinon il se retrouve considéré dans l'entête, mon bouton se trouvait hors composant donc non fonctionnel. J'ai du faire appel à L'IA pour comprendre l'errance, mais j'ai apprécié l'explication détaillée du problème, surtout en prévision du passkey et de la refonte du visuel, je devrai garder en tête ces points. 
# La BD
Autre gros chantier du projet de production la base de donnée. Je ne peux pas rester en sqlite, je passe au Cloud, alors je dois migrer vers autre chose. Cette autre chose a fini par être PostgreSQL, ce qui m'a demandé d'installer DBNGIN avec un dossier de bd local, puis une grosse conversion des migrations. Pour faire plus propre une migration par table et on sort les données, mais cela veut aussi dire de nombreuses corrections. En chemin j'ai effacé le dossier de database en voulant faire mon setup de DBNGIN, merci github qui m'a permis de restaurer les fichiers ainsi perdus (factories, migrations, seeders et schema), je n'ose pas imaginer le bordel que ça aurait fait sans la restauration. J'ai même zappé ma BD sqlite au passage puisque tout tient dans un fichier. Heureusement que c'était un env. de test parce que cette BD n'est pas dans le git. 
# Le déploiement
Bien sur, loin d'être au bout de mes peines, il a fallu finir par déployer tout ça en prod. Une fois le compte créé, la partie "amusante" commence, trouver les endroits pour activer ce dont on a besoin, le scheduler, le worker, construire une BD la lier, se rendre compte qu'il n'y a pas de serveur PostgreSQL au Canada sur la plateforme, fallback vers MySQL possible cependant, je l'ai noté, en env. réel j'aurais reculé et repassé à MySQL pour respecter la loi 25 et éviter des soucis, mais comme l'appli est fake et que je ne voulais pas reconstruire ma BD une 3e fois j'ai décidé de continuer et de noter dans l'ADR.  
J'ai encore failli écraser une modification en oubliant de réimporter post merge de PR. Ce n'est pas la première fois que je cafouille ainsi, heureusement, github me rappelle à l'ordre, et l'appli de bureau me permet de "stash" mes changements.  
J'ai eu un peu de difficulté à finaliser les tests pour compléter mon ADR-0014, mais au final le dump fonctionne et tout est bien qui finit bien, mon application est officiellement déployée.
Au final j'aurai mis toute une journée (près de 8 heures) de développement pour simplement prendre mon app locale et l'envoyer dans le Cloud, sans avoir été d'une grande difficulté impossible à surmonter, ç'aura été un test de patience, truffé d'erreurs de parcours, de bogue de déploiement en production et d'apprentissage d'un nouvel environnement.