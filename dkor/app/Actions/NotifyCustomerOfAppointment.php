<?php

namespace App\Actions;

use App\Enums\CustomerNoticeType;
use App\Models\Appointment;
use App\Models\customer;
use Illuminate\Support\Facades\DB;

/**
 * Avise le client de son rendez-vous (issue #138) : confirmé, déplacé, annulé ou rappel de la veille. Rien n'est
 * envoyé pour un rendez-vous sans client ou déjà commencé, et seulement une fois la transaction confirmée.
 */
class NotifyCustomerOfAppointment
{
    public function __construct(private NotifyCustomer $notifyCustomer) {}

    public function handle(Appointment $appointment, CustomerNoticeType $type): void
    {
        if ($appointment->customer_id === null || $appointment->startsAt()->isPast()) {
            return;
        }

        DB::afterCommit(function () use ($appointment, $type): void {
            $customer = customer::find($appointment->customer_id);

            if ($customer === null) {
                return;
            }

            $this->notifyCustomer->handle(
                $customer,
                $type,
                eventKey: $this->eventKey($appointment, $type),
                createdBy: auth()->id(),
                subject: $appointment,
            );
        });
    }

    /**
     * Clé de l'événement : un déplacement par nouvelle heure, un rappel par date, une confirmation par client.
     */
    private function eventKey(Appointment $appointment, CustomerNoticeType $type): string
    {
        return $type->value.':'.$appointment->id.':'.match ($type) {
            CustomerNoticeType::AppointmentBooked => $appointment->customer_id,
            CustomerNoticeType::AppointmentMoved => $appointment->startsAt()->format('YmdHi').':'.$appointment->updated_at?->getTimestamp(),
            CustomerNoticeType::AppointmentReminder => $appointment->startsAt()->format('Ymd'),
            default => 'once',
        };
    }
}
