<?php

namespace Database\Seeders;

use App\Models\CustomerPaymentMethod;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Données dont l'application a besoin pour fonctionner : permissions, rôles par défaut (config/access.php)
     * mode de paiement « Comptant » et service « Frais d'expédition ». Sans effet sur ce qui existe déjà; utilisé aussi par les tests.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        CustomerPaymentMethod::cash();
        Service::shippingFee();
    }
}
