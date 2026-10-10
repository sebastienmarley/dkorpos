<?php

namespace Database\Seeders;

use App\Models\CustomerPaymentMethod;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Données dont l'application a besoin pour fonctionner : permissions, rôles par défaut (config/access.php)
     * et mode de paiement « Comptant ». Sans effet sur ce qui existe déjà; utilisé aussi par les tests.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        CustomerPaymentMethod::cash();
    }
}
