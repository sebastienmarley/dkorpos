<?php

/*
|--------------------------------------------------------------------------
| Paie
|--------------------------------------------------------------------------
|
| Les périodes de paie durent 2 semaines et s'enchaînent à partir de `period_anchor`,
| un dimanche (premier jour de la semaine des horaires).
|
*/

return [
    'period_anchor' => env('PAYROLL_PERIOD_ANCHOR', '2026-01-04'),

    'period_days' => 14,

    /*
     * Début de l'année de maladie (MM-JJ) quand le magasin de l'employé n'en définit pas.
     */
    'default_sick_accrual_start' => '01-01',
];
