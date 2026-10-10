<?php

/*
|--------------------------------------------------------------------------
| Ventes
|--------------------------------------------------------------------------
|
| Dépôt minimum exigé sur les articles d'une commande client qui ne sont pas encore remis au client. Un article
| ramassé ou livré doit être payé à 100 %. Les taxes viennent de la page Comptabilité > Taxes, selon la province
| du magasin de la commande.
|
*/

return [
    'deposit_percent' => (float) env('SALES_DEPOSIT_PERCENT', 30.0),
];
