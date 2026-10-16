<?php

/*
|--------------------------------------------------------------------------
| Horaires et rendez-vous
|--------------------------------------------------------------------------
|
| Dans la semaine en cours, un rendez-vous se prend seulement pendant un quart publié. Les semaines
| suivantes, un rendez-vous peut être pris sans quart publié pendant un nombre de jours limité : le
| gestionnaire de l'horaire voit alors la plage à couvrir. Au-delà, il faut un quart publié.
|
*/

return [
    /*
     * Nombre de jours, comptés après le samedi de la semaine en cours, où un rendez-vous peut être pris
     * sans quart publié.
     */
    'unscheduled_appointment_days' => 14,
];
