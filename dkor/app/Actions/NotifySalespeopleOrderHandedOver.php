<?php

namespace App\Actions;

use App\Models\CustomerOrder;
use App\Models\User;
use App\Notifications\CustomerOrderHandedOverNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Avise les vendeurs actifs de la commande que leur client a tout reçu (issue #137), une fois la transaction
 * confirmée. L'usager qui remet les articles n'est pas avisé.
 */
class NotifySalespeopleOrderHandedOver
{
    public function handle(CustomerOrder $order): void
    {
        DB::afterCommit(function () use ($order): void {
            $order = $order->fresh(['customer']);

            Notification::send(
                $order->salespeople()->where('is_active', true)->get()->reject(fn (User $user): bool => $user->id === auth()->id()),
                new CustomerOrderHandedOverNotice($order),
            );
        });
    }
}
