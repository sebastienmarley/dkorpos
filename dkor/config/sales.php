<?php

/*
|--------------------------------------------------------------------------
| Ventes
|--------------------------------------------------------------------------
|
| Taux des taxes de vente (en %) et dépôt minimum exigé sur les articles d'une commande client qui ne sont
| pas encore remis au client. Un article ramassé ou livré doit être payé à 100 %.
|
*/

return [
    'taxes' => [
        'gst' => (float) env('SALES_GST_RATE', 5.0),
        'qst' => (float) env('SALES_QST_RATE', 9.975),
    ],

    'deposit_percent' => (float) env('SALES_DEPOSIT_PERCENT', 30.0),
];
