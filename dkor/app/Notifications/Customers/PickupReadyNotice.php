<?php

namespace App\Notifications\Customers;

use App\Models\customer;
use App\Models\CustomerOrder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avis au client : sa commande est prête à ramasser (issue #137), avec l'adresse, les heures d'ouverture du magasin et
 * le solde à payer.
 */
class PickupReadyNotice extends Notification
{
    public function __construct(public CustomerOrder $order) {}

    /** @return list<string> */
    public function via(customer $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(customer $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('store');
        $store = $order->store;

        $message = (new MailMessage)
            ->subject(__('Votre commande #:id est prête à ramasser', ['id' => $order->id]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->firstname]))
            ->line(__('Bonne nouvelle : tous les articles de votre commande #:id sont arrivés. Vous pouvez venir les ramasser.', ['id' => $order->id]));

        if ($store !== null) {
            if (filled($store->email)) {
                $message->replyTo($store->email, $store->name);
            }

            $address = trim(trim($store->address_civic.' '.$store->address_street).', '.trim($store->address_city.' '.$store->address_province.' '.$store->address_postal_code), ', ');

            $message->line(__('Magasin : :store', ['store' => $store->name]))
                ->lineIf(filled($address), __('Adresse : :address', ['address' => $address]))
                ->line(__('Heures d\'ouverture :'));

            foreach ($store->openingHoursLines() as $hours) {
                $message->line($hours);
            }
        }

        $message->line($order->balance_due->isPositive()
            ? __('Solde à payer au ramassage : :amount', ['amount' => $order->balance_due->format()])
            : __('Votre commande est payée au complet.'));

        if (filled($store?->phone)) {
            $message->line(__('Pour toute question, communiquez avec nous au :phone.', ['phone' => $store->phone]));
        }

        return $message->salutation(__('Merci de votre confiance!'));
    }
}
