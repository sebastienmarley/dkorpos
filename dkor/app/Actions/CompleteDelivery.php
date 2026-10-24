<?php

namespace App\Actions;

use App\Models\CustomerOrderDelivery;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Termine une livraison en route une fois la réception confirmée et signée par le client (issues #93 et #94) : les
 * articles acceptés sont livrés, les refusés retournent en stock client et les vendeurs sont avisés. L'image de la
 * signature est rangée sur un disque privé avec le nom du signataire et le moment de la signature.
 */
class CompleteDelivery
{
    /** Taille maximale de l'image de la signature, en octets. */
    public const int MAX_SIGNATURE_BYTES = 1_000_000;

    public function __construct(private NotifyDeliverySalespeople $notifySalespeople) {}

    /**
     * @param  array<int, int>  $accepted  quantité acceptée par ligne de livraison
     * @param  array<int, string>  $refusalReasons  raison du refus par ligne de livraison
     * @param  string  $signature  image PNG de la signature, en URL de données (data:image/png;base64,…)
     *
     * @throws DomainException
     */
    public function handle(CustomerOrderDelivery $delivery, array $accepted, array $refusalReasons, string $signedByName, string $signature): void
    {
        if (blank($signedByName)) {
            throw new DomainException(__('Indiquez le nom de la personne qui signe.'));
        }

        $image = $this->decodeSignature($signature);
        $disk = Storage::disk(config('deliveries.signature_disk'));
        $path = 'signatures/deliveries/'.$delivery->id.'-'.now()->format('YmdHis').'.png';

        $disk->put($path, $image);

        try {
            $order = $delivery->order()->firstOrFail();
            $refused = $order->completeDelivery($delivery, $accepted, $refusalReasons);
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        $delivery->refresh()->update(['signature_path' => $path, 'signed_by_name' => trim($signedByName), 'signed_at' => now()]);

        $this->notifySalespeople->handle($delivery, __('Articles refusés à la livraison — commande #:id', ['id' => $order->id]), $refused);
    }

    /**
     * Image PNG de la signature, refusée si elle est vide, trop lourde ou n'est pas un PNG.
     *
     * @throws DomainException
     */
    private function decodeSignature(string $signature): string
    {
        $prefix = 'data:image/png;base64,';
        $image = str_starts_with($signature, $prefix) ? base64_decode(substr($signature, strlen($prefix)), true) : false;

        if ($image === false || $image === '' || ! str_starts_with($image, "\x89PNG\r\n\x1a\n")) {
            throw new DomainException(__('Faites signer le client avant de terminer.'));
        }

        if (strlen($image) > self::MAX_SIGNATURE_BYTES) {
            throw new DomainException(__('L\'image de la signature est trop lourde.'));
        }

        return $image;
    }
}
