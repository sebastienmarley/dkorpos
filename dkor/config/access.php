<?php

/*
|--------------------------------------------------------------------------
| Rôles et permissions par défaut
|--------------------------------------------------------------------------
|
| Définition de départ utilisée par PermissionSeeder et RoleSeeder. Les permissions
| sont les clés utilisées par les gates et les policies de l'application. Les
| rôles sont ensuite gérés en base (niveau hiérarchique, permissions de base).
|
| Une permission ajoutée ici est créée au prochain déploiement (ReferenceDataSeeder)
| et accordée aux rôles existants qui la listent, sans toucher aux autres permissions.
|
*/

$pageAccess = [
    'customers.view',
    'customers.create',
    'customers.edit',
    'customer_orders.view',
    'customer_orders.create',
    'customer_orders.edit',
    'services.view',
    'products.view',
    'products.create',
    'inventory.view',
    'schedules.view',
    'appointments.view',
    'appointments.create',
    'appointments.edit',
    'appointments.delete',
];

$managementAccess = [
    'customer_orders.assign_salespeople',
    'services.view',
    'services.create',
    'services.edit',
];

$warehouseAccess = [
    'suppliers.view',
    'supplier_orders.view',
    'receptions.view',
    'receptions.create',
    'receptions.reverse',
    'schedules.view',
    'appointments.view',
];

$accountingAccess = [
    'services.view',
    'services.create',
    'services.edit',
    'payment_methods.view',
    'payment_methods.create',
    'payment_methods.edit',
    'taxes.view',
    'taxes.create',
    'taxes.edit',
    'price_lists.view',
    'price_lists.create',
    'price_lists.edit',
    'payroll.view',
    'payroll.lock',
    'users.view',
    'users.view_hr',
    'users.edit_hr',
    'suppliers.view',
    'suppliers.create',
    'suppliers.edit',
    'supplier_orders.view',
    'supplier_orders.create',
    'supplier_orders.edit',
    'supplier_orders.delete',
    'receptions.view',
    'receptions.create',
    'receptions.reverse',
    'invoices.view',
    'invoices.create',
    'invoices.edit',
    'invoices.delete',
];

return [
    /*
     * Permissions de pages et d'actions accordées par défaut à tous les rôles
     * (accès actuel conservé, les restrictions se font dans l'interface).
     */
    'page_access' => $pageAccess,
    'warehouse_access' => $warehouseAccess,
    'accounting_access' => $accountingAccess,

    'permissions' => [
        'users.view' => ['Voir les utilisateurs', 'Consulter la liste des utilisateurs.'],
        'users.create' => ['Créer un utilisateur', 'Ajouter un nouvel employé.'],
        'users.edit' => ['Modifier un utilisateur', 'Modifier la fiche, le statut et le mot de passe d\'un employé.'],
        'users.edit_self' => ['Modifier sa propre fiche', 'Modifier sa propre fiche utilisateur.'],
        'users.view_hr' => ['Voir les informations RH', 'Consulter l\'onglet RH (temps plein, assurances, salaire, commission) d\'un employé.'],
        'users.edit_hr' => ['Modifier les informations RH', 'Modifier l\'onglet RH d\'un employé (sauf le sien).'],
        'users.assign_permissions' => ['Attribuer des permissions supplémentaires', 'Ajouter des permissions à un utilisateur en plus de celles de son rôle.'],
        'customers.view' => ['Voir — clients', 'Accéder à la page : clients.'],
        'customers.create' => ['Créer — clients', 'Créer dans : clients.'],
        'customers.edit' => ['Modifier — clients', 'Modifier dans : clients.'],
        'customer_orders.view' => ['Voir — commandes clients', 'Accéder à la page : commandes clients.'],
        'customer_orders.create' => ['Créer — commandes clients', 'Créer dans : commandes clients.'],
        'customer_orders.edit' => ['Modifier — commandes clients', 'Modifier dans : commandes clients (client, produits).'],
        'customer_orders.assign_salespeople' => ['Gérer les vendeurs — commandes clients', 'Modifier les vendeurs et la répartition de la vente dans : commandes clients.'],
        'suppliers.view' => ['Voir — fournisseurs', 'Accéder à la page : fournisseurs.'],
        'suppliers.create' => ['Créer — fournisseurs', 'Créer dans : fournisseurs.'],
        'suppliers.edit' => ['Modifier — fournisseurs', 'Modifier dans : fournisseurs.'],
        'products.view' => ['Voir — produits', 'Accéder à la page : produits.'],
        'products.create' => ['Créer — produits', 'Créer dans : produits.'],
        'products.edit' => ['Modifier — produits', 'Modifier dans : produits.'],
        'supplier_orders.view' => ['Voir — commandes fournisseurs', 'Accéder à la page : commandes fournisseurs.'],
        'supplier_orders.create' => ['Créer — commandes fournisseurs', 'Créer dans : commandes fournisseurs.'],
        'supplier_orders.edit' => ['Modifier — commandes fournisseurs', 'Modifier dans : commandes fournisseurs (lignes, envoi, réception, facture, annulation).'],
        'supplier_orders.delete' => ['Supprimer — commandes fournisseurs', 'Supprimer dans : commandes fournisseurs (brouillons seulement).'],
        'receptions.view' => ['Voir — réceptions', 'Accéder à la page : réceptions.'],
        'receptions.create' => ['Créer — réceptions', 'Créer dans : réceptions (réceptionner des produits commandés).'],
        'receptions.reverse' => ['Renverser — réceptions', 'Renverser une réception de produits non encore facturée (erreur de réception).'],
        'inventory.view' => ['Voir — journal d\'inventaire', 'Accéder à la page : journal d\'inventaire.'],
        'inventory.move' => ['Déplacer — inventaire', 'Déplacer des quantités entre états d\'inventaire (démo, défectueux, perdu…).'],
        'services.view' => ['Voir — services', 'Accéder à la page : services.'],
        'services.create' => ['Créer — services', 'Créer dans : services.'],
        'services.edit' => ['Modifier — services', 'Modifier dans : services (fournisseurs et prix).'],
        'departments.view' => ['Voir — départements', 'Accéder à la page : départements.'],
        'departments.create' => ['Créer — départements', 'Créer dans : départements.'],
        'departments.edit' => ['Modifier — départements', 'Modifier dans : départements.'],
        'categories.view' => ['Voir — catégories', 'Accéder à la page : catégories.'],
        'categories.create' => ['Créer — catégories', 'Créer dans : catégories.'],
        'categories.edit' => ['Modifier — catégories', 'Modifier dans : catégories.'],
        'colors.view' => ['Voir — couleurs', 'Accéder à la page : couleurs.'],
        'colors.create' => ['Créer — couleurs', 'Créer dans : couleurs.'],
        'colors.edit' => ['Modifier — couleurs', 'Modifier dans : couleurs.'],
        'payment_methods.view' => ['Voir — modes de paiement', 'Accéder à la page : modes de paiement.'],
        'payment_methods.create' => ['Créer — modes de paiement', 'Créer dans : modes de paiement.'],
        'payment_methods.edit' => ['Modifier — modes de paiement', 'Modifier et désactiver dans : modes de paiement.'],
        'taxes.view' => ['Voir — taxes', 'Accéder à la page : taxes.'],
        'taxes.create' => ['Créer — taxes', 'Créer dans : taxes.'],
        'taxes.edit' => ['Expirer — taxes', 'Faire expirer une taxe (le taux d\'une taxe sauvegardée ne se modifie pas).'],
        'currencies.view' => ['Voir — devises', 'Accéder à la page : devises.'],
        'currencies.create' => ['Créer — devises', 'Créer dans : devises.'],
        'currencies.edit' => ['Modifier — devises', 'Modifier dans : devises.'],
        'invoices.view' => ['Voir — facturation fournisseurs', 'Accéder à la page : facturation fournisseurs.'],
        'invoices.create' => ['Créer — facturation fournisseurs', 'Saisir la facture d\'une réception.'],
        'invoices.edit' => ['Modifier — facturation fournisseurs', 'Modifier une facture fournisseur existante.'],
        'invoices.delete' => ['Supprimer — facturation fournisseurs', 'Supprimer une facture fournisseur.'],
        'schedules.view' => ['Voir — « Mon horaire »', 'Accéder à la page : « Mon horaire ».'],
        'schedule_management.view' => ['Voir — gestion des horaires', 'Accéder à la page : gestion des horaires.'],
        'schedule_management.edit' => ['Modifier — gestion des horaires', 'Modifier dans : gestion des horaires.'],
        'schedule_management.publish' => ['Publier — gestion des horaires', 'Publier dans : gestion des horaires.'],
        'schedule_templates.view' => ['Voir — modèles d\'horaire', 'Accéder à la page : modèles d\'horaire.'],
        'schedule_templates.create' => ['Créer — modèles d\'horaire', 'Créer dans : modèles d\'horaire.'],
        'schedule_templates.edit' => ['Modifier — modèles d\'horaire', 'Modifier dans : modèles d\'horaire.'],
        'schedule_templates.delete' => ['Supprimer — modèles d\'horaire', 'Supprimer dans : modèles d\'horaire.'],
        'holidays.view' => ['Voir — jours fériés', 'Accéder à la page : jours fériés.'],
        'holidays.create' => ['Créer — jours fériés', 'Créer dans : jours fériés.'],
        'holidays.edit' => ['Modifier — jours fériés', 'Modifier dans : jours fériés.'],
        'holidays.delete' => ['Supprimer — jours fériés', 'Supprimer dans : jours fériés.'],
        'appointments.view' => ['Voir — rendez-vous', 'Accéder à la page : rendez-vous.'],
        'appointments.create' => ['Créer — rendez-vous', 'Créer dans : rendez-vous.'],
        'appointments.edit' => ['Modifier — rendez-vous', 'Modifier dans : rendez-vous.'],
        'appointments.delete' => ['Supprimer — rendez-vous', 'Supprimer dans : rendez-vous.'],
        'price_lists.view' => ['Voir — listes de prix', 'Accéder à la page : listes de prix.'],
        'price_lists.create' => ['Créer — listes de prix', 'Créer dans : listes de prix.'],
        'price_lists.edit' => ['Modifier — listes de prix', 'Modifier et archiver dans : listes de prix.'],
        'payroll.view' => ['Voir — paie', 'Accéder à la page de paie et télécharger le rapport.'],
        'payroll.lock' => ['Verrouiller — paie', 'Verrouiller et déverrouiller les horaires d\'une période de paie.'],
        'roles.manage' => ['Gérer les rôles', 'Créer et modifier les rôles et leurs permissions.'],
        'permissions.manage' => ['Gérer les permissions', 'Créer et modifier les permissions.'],
        'stores.view' => ['Voir — magasins', 'Accéder à la page : magasins.'],
        'stores.create' => ['Créer — magasins', 'Créer dans : magasins.'],
        'stores.edit' => ['Modifier — magasins', 'Modifier dans : magasins.'],
        'positions.manage' => ['Gérer les positions', 'Créer et modifier les titres d\'emploi.'],
    ],

    'roles' => [
        'admin' => ['label' => 'Administrateur', 'level' => 100, 'permissions' => '*'],
        'owner' => ['label' => 'Propriétaire', 'level' => 100, 'permissions' => '*'],
        'manager' => ['label' => 'Directeur', 'level' => 50, 'permissions' => ['users.view', 'users.create', 'users.edit', ...$pageAccess, ...$managementAccess]],
        'design' => ['label' => 'Designer', 'level' => 10, 'permissions' => ['users.view', ...$pageAccess]],
        'delivery' => ['label' => 'Livreur', 'level' => 10, 'permissions' => ['users.view', ...$pageAccess]],
        'warehouse' => ['label' => 'Commis entrepôt', 'level' => 10, 'permissions' => [...$warehouseAccess]],
        'salesman' => ['label' => 'Vendeur', 'level' => 10, 'permissions' => [...$pageAccess]],
        'accounting' => ['label' => 'Comptabilité', 'level' => 10, 'permissions' => [...$accountingAccess]],
        'thirdkey' => ['label' => 'Troisième clé', 'level' => 10, 'permissions' => ['users.view', ...$pageAccess]],
    ],
];
