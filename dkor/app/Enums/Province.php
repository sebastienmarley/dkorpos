<?php

namespace App\Enums;

enum Province: string
{
    case Alberta = 'AB';
    case BritishColumbia = 'BC';
    case Manitoba = 'MB';
    case NewBrunswick = 'NB';
    case NewfoundlandAndLabrador = 'NL';
    case NovaScotia = 'NS';
    case NorthwestTerritories = 'NT';
    case Nunavut = 'NU';
    case Ontario = 'ON';
    case PrinceEdwardIsland = 'PE';
    case Quebec = 'QC';
    case Saskatchewan = 'SK';
    case Yukon = 'YT';

    public function label(): string
    {
        return match ($this) {
            self::Alberta => 'Alberta',
            self::BritishColumbia => 'Colombie-Britannique',
            self::Manitoba => 'Manitoba',
            self::NewBrunswick => 'Nouveau-Brunswick',
            self::NewfoundlandAndLabrador => 'Terre-Neuve-et-Labrador',
            self::NovaScotia => 'Nouvelle-Écosse',
            self::NorthwestTerritories => 'Territoires du Nord-Ouest',
            self::Nunavut => 'Nunavut',
            self::Ontario => 'Ontario',
            self::PrinceEdwardIsland => 'Île-du-Prince-Édouard',
            self::Quebec => 'Québec',
            self::Saskatchewan => 'Saskatchewan',
            self::Yukon => 'Yukon',
        };
    }
}
