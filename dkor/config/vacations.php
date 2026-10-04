<?php

/*
|--------------------------------------------------------------------------
| Vacances
|--------------------------------------------------------------------------
|
| Paramètres du calcul des vacances (Loi sur les normes du travail du Québec).
| L'année de référence est commune à toute l'entreprise : le cumul repart à zéro
| à chaque début d'année de référence.
|
*/

return [
    'reference_year_start' => ['month' => 5, 'day' => 1],

    'default_hours_per_day' => 8,

    /*
     * Paliers selon l'ancienneté (années de service continu) : jours ouvrables par année de référence.
     * Moins d'un an : 1 jour par mois de service, jusqu'à `first_year_max_days`.
     */
    'tiers' => [
        3 => 15,
        1 => 10,
    ],

    'first_year_max_days' => 10,
];
