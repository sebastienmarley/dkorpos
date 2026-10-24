<?php

namespace App\Actions;

use App\Models\StoreTransfer;
use App\Models\User;
use App\Notifications\StoreTransferNotice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Avise par la cloche les usagers concernés par un transfert entre magasins (issue #103) : ceux d'un magasin qui ont la
 * permission voulue, le demandeur ou les vendeurs de la commande client. L'usager qui agit n'est pas avisé.
 */
class NotifyStoreTransfer
{
    /** Avise les usagers actifs d'un magasin qui ont la permission. */
    public function toStore(StoreTransfer $transfer, int $storeId, string $permission, string $title): void
    {
        $this->send(
            User::query()->where('is_active', true)->where('store_id', $storeId)->get()->filter(fn (User $user): bool => $user->can($permission))->values(),
            $transfer,
            $title,
        );
    }

    public function toRequester(StoreTransfer $transfer, string $title): void
    {
        $this->send($transfer->requester()->get(), $transfer, $title);
    }

    /** Avise les vendeurs de la commande servie par le transfert (transfert pour un client). */
    public function toSalespeople(StoreTransfer $transfer, string $title): void
    {
        $order = $transfer->line?->order;

        if ($order !== null) {
            $this->send($order->salespeople()->where('is_active', true)->get(), $transfer, $title);
        }
    }

    /** @param Collection<int, User> $users */
    private function send(Collection $users, StoreTransfer $transfer, string $title): void
    {
        $recipients = $users->reject(fn (User $user): bool => $user->id === auth()->id());

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new StoreTransferNotice($transfer, $title));
        }
    }
}
