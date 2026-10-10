# ADR 0003 — Calendrier de rendez-vous dynamique maison en Alpine.js

- **Statut :** Accepté
- **Date :** 2026-10-05

## Contexte

Il faut gérer rapidement l'horaire de plusieurs employés.

Une panoplie d'outils externes offre déjà ce service : Google Calendar, l'agenda de Microsoft Outlook,
Calendly, GoRendezVous, etc. Pour en faire un maison, il faut s'appuyer sur leurs meilleures
pratiques :

- facile à manipuler, toutes les actions à portée de clic ;
- facile d'ajouter, de modifier et de déplacer un rendez-vous, voire de le transférer.

## Options considérées

### Option A — Calendrier statique partagé, à la Google

Chaque personne a son calendrier, et on se les partage.

- ✅ Modèle connu de tous
- ❌ Plein de couleurs superposées : on ne comprend plus rien
- ❌ Les livraisons et les infos clients s'ajoutent au calendrier, mais on n'y a pas accès directement

### Option B — Calendrier dynamique maison en Alpine.js ✔️

Les rendez-vous sont des blocs de réservation qu'on manipule directement.

- ✅ On allonge ou rétrécit un rendez-vous en cliquant et en glissant le bloc
- ✅ On déplace un bloc ailleurs dans la semaine
- ✅ Intégré à l'application : accès direct aux données du magasin
- ❌ Composant maison à développer et à maintenir

## Décision

Nous retenons l'**option B** : un calendrier dynamique en Alpine.js, avec des blocs de réservation
manipulables par glisser-déposer.

## Conséquences

**Positives**

- Les opérations courantes (ajouter, déplacer, allonger un rendez-vous) se font sans formulaire.

**Négatives / limitations**

- Le comportement du glisser-déposer est à notre charge (tests, compatibilité).

**Protections mises en place**

- Les jours passés sont bloqués.
- Les jours où l'employé ne travaille pas sont bloqués.

## Références

- Alpine.js
