<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrderDelivery;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Image de la signature du client d'une livraison, servie depuis le disque privé (issue #93).
 */
class DeliverySignatureController extends Controller
{
    public function __invoke(CustomerOrderDelivery $delivery): StreamedResponse
    {
        $disk = Storage::disk(config('deliveries.signature_disk'));

        abort_if($delivery->signature_path === null || ! $disk->exists($delivery->signature_path), 404);

        return $disk->response($delivery->signature_path, 'signature-livraison-'.$delivery->id.'.png', ['Cache-Control' => 'private, max-age=3600']);
    }
}
