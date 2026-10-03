<?php

return [
    /*
     * Active tous les courriels aux fournisseurs (commande envoyée, demande d'annulation de ligne).
     * Désactivé tant qu'aucun serveur de courriel n'est configuré: les actions se font sans courriel.
     */
    'email_enabled' => (bool) env('SUPPLIER_ORDER_EMAIL_ENABLED', false),
];
