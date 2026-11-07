<?php

namespace App\Actions;

use App\Enums\CustomerNoticeType;
use App\Models\Appointment;
use Illuminate\Support\Carbon;

/**
 * Rappelle aux clients leurs rendez-vous du lendemain (issue #138), un seul rappel par rendez-vous et par date.
 */
class SendAppointmentReminders
{
    public function __construct(private NotifyCustomerOfAppointment $notifyCustomer) {}

    /**
     * @return int nombre de rendez-vous traités
     */
    public function handle(): int
    {
        $appointments = Appointment::query()
            ->whereNotNull('customer_id')
            ->whereDate('date', Carbon::tomorrow())
            ->orderBy('start_minute')
            ->orderBy('id')
            ->get();

        $appointments->each(fn (Appointment $appointment) => $this->notifyCustomer->handle($appointment, CustomerNoticeType::AppointmentReminder));

        return $appointments->count();
    }
}
