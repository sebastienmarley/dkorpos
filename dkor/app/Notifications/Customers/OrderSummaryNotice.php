<?php

namespace App\Notifications\Customers;

use App\Mail\CustomerOrderSummary;
use App\Models\customer;
use App\Models\CustomerOrder;
use Illuminate\Notifications\Notification;

/**
 * Commande envoyée au client (gabarit de la commande imprimée).
 */
class OrderSummaryNotice extends Notification
{
    public function __construct(public CustomerOrder $order) {}

    /** @return list<string> */
    public function via(customer $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(customer $notifiable): CustomerOrderSummary
    {
        return (new CustomerOrderSummary($this->order->loadDocument()))->to($notifiable->email);
    }
}
