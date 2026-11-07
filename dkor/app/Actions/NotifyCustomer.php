<?php

namespace App\Actions;

use App\Enums\CustomerNoticeStatus;
use App\Enums\CustomerNoticeType;
use App\Models\Appointment;
use App\Models\customer;
use App\Models\CustomerNotice;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use App\Notifications\Customers\AppointmentNotice;
use App\Notifications\Customers\DeliveryUpdateNotice;
use App\Notifications\Customers\OrderSummaryNotice;
use App\Notifications\Customers\PickupReadyNotice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Envoie un avis à un client et l'inscrit à l'historique (issue #135). Un avis automatique ne part pas si le client
 * les refuse ; un client sans courriel ne reçoit rien, et l'historique le signale pour qu'on l'appelle. Une clé
 * d'événement déjà inscrite n'envoie rien : le même événement ne produit jamais deux avis.
 */
class NotifyCustomer
{
    /**
     * @return CustomerNotice|null null si l'événement a déjà son avis
     */
    public function handle(customer $customer, CustomerNoticeType $type, ?CustomerOrder $order = null, ?string $eventKey = null, int|string|null $createdBy = null, ?Model $subject = null): ?CustomerNotice
    {
        if ($eventKey !== null && CustomerNotice::query()->where('event_key', $eventKey)->exists()) {
            return null;
        }

        $blockedStatus = $this->blockingStatus($customer, $type);

        try {
            $notice = DB::transaction(fn (): CustomerNotice => CustomerNotice::create([
                'customer_id' => $customer->id,
                'customer_order_id' => $order?->id,
                'type' => $type,
                'event_key' => $eventKey,
                'recipient' => $customer->email,
                'status' => $blockedStatus ?? CustomerNoticeStatus::Failed,
                'failed_at' => $blockedStatus === null ? null : now(),
                'created_by' => $createdBy,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if ($blockedStatus !== null) {
            return $notice;
        }

        return $this->deliver($notice->setRelation('customer', $customer)->setRelation('order', $order)->setRelation('subject', $subject));
    }

    /**
     * Renvoie un avis échoué au courriel actuel du client.
     */
    public function resend(CustomerNotice $notice): CustomerNotice
    {
        if (! $notice->isResendable()) {
            return $notice;
        }

        $notice->update(['recipient' => $notice->customer->email]);

        if (blank($notice->customer->email)) {
            $notice->update(['status' => CustomerNoticeStatus::NoEmail, 'failed_at' => now()]);

            return $notice;
        }

        return $this->deliver($notice);
    }

    private function blockingStatus(customer $customer, CustomerNoticeType $type): ?CustomerNoticeStatus
    {
        return match (true) {
            $type->isAutomatic() && $customer->notifications_opt_out => CustomerNoticeStatus::OptedOut,
            blank($customer->email) => CustomerNoticeStatus::NoEmail,
            default => null,
        };
    }

    private function deliver(CustomerNotice $notice): CustomerNotice
    {
        try {
            $notice->customer->notifyNow($this->notificationFor($notice));
        } catch (Throwable $e) {
            report($e);
            $notice->update(['status' => CustomerNoticeStatus::Failed, 'sent_at' => null, 'failed_at' => now()]);

            return $notice;
        }

        $notice->update(['status' => CustomerNoticeStatus::Sent, 'sent_at' => now(), 'failed_at' => null]);

        return $notice;
    }

    private function notificationFor(CustomerNotice $notice): Notification
    {
        return match ($notice->type) {
            CustomerNoticeType::OrderSummary => new OrderSummaryNotice($notice->order),
            CustomerNoticeType::DeliveryScheduled,
            CustomerNoticeType::DeliveryRescheduled,
            CustomerNoticeType::DeliveryCancelled,
            CustomerNoticeType::DeliveryOnTheWay,
            CustomerNoticeType::ShipmentSent => new DeliveryUpdateNotice($this->subjectOf($notice, CustomerOrderDelivery::class), $notice->type),
            CustomerNoticeType::PickupReady => new PickupReadyNotice($notice->order),
            CustomerNoticeType::AppointmentBooked,
            CustomerNoticeType::AppointmentMoved,
            CustomerNoticeType::AppointmentCancelled,
            CustomerNoticeType::AppointmentReminder => new AppointmentNotice($this->subjectOf($notice, Appointment::class), $notice->type),
        };
    }

    /**
     * Document dont parle l'avis, du type attendu pour sa sorte d'avis.
     *
     * @template TSubject of Model
     *
     * @param  class-string<TSubject>  $class
     * @return TSubject
     *
     * @throws LogicException si l'avis ne porte pas sur un document de ce type
     */
    private function subjectOf(CustomerNotice $notice, string $class): Model
    {
        $subject = $notice->subject;

        if (! $subject instanceof $class) {
            throw new LogicException(__('L\'avis #:id ne porte pas sur le document attendu.', ['id' => $notice->id]));
        }

        return $subject;
    }
}
