<?php

namespace App\Notifications;

use App\Models\StoreTransfer;
use Illuminate\Notifications\Notification;

/**
 * Avis sur un transfert entre magasins (issue #103) : demandé, accepté, refusé, annulé ou reçu.
 */
class StoreTransferNotice extends Notification
{
    public const string Type = 'store-transfer';

    public function __construct(public StoreTransfer $transfer, public string $title) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return self::Type;
    }

    /**
     * Forme commune des notifications affichées par la cloche.
     *
     * @return array{title: string, body: string, url: string, icon: string}
     */
    public function toArray(object $notifiable): array
    {
        $this->transfer->loadMissing(['product', 'fromStore', 'toStore', 'line.order.customer']);
        $customer = $this->transfer->line?->order->customer;

        return [
            'title' => $this->title,
            'body' => __(':quantity × :product — :from → :to', [
                'quantity' => $this->transfer->quantity,
                'product' => $this->transfer->product->model,
                'from' => $this->transfer->fromStore->name,
                'to' => $this->transfer->toStore->name,
            ]).($customer ? ' · '.__('commande #:order (:customer)', ['order' => $this->transfer->line->customer_order_id, 'customer' => $customer->firstname.' '.$customer->lastname]) : ''),
            'url' => route('inventory.transfers'),
            'icon' => 'arrows-right-left',
        ];
    }
}
