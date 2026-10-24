<?php

/*
|--------------------------------------------------------------------------
| Inventaire
|--------------------------------------------------------------------------
|
| Paramètres de l'inventaire par magasin (ADR 0012).
|
*/

return [

    /*
    | Premier jour de l'année fiscale (MM-JJ). Chaque magasin doit avoir au moins une prise d'inventaire finalisée
    | par année fiscale : du 1er février au 31 janvier.
    */
    'fiscal_year_start' => env('INVENTORY_FISCAL_YEAR_START', '02-01'),

];
