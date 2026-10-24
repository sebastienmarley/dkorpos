<?php

/*
|--------------------------------------------------------------------------
| Livraisons
|--------------------------------------------------------------------------
|
| Paramètres des livraisons par nos camions (ADR 0010).
|
*/

return [

    /*
    | Nombre de jours pendant lesquels l'image de la signature du client est conservée après la livraison (issue
    | #93). La livraison garde ensuite le nom du signataire, la date et la liste des articles.
    */
    'signature_retention_days' => (int) env('DELIVERY_SIGNATURE_RETENTION_DAYS', 365),

    /*
    | Disque privé où sont rangées les signatures (jamais un disque public).
    */
    'signature_disk' => env('DELIVERY_SIGNATURE_DISK', 'local'),

];
