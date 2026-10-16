<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\User;
use App\Support\AppointmentBooking;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Réassigne un rendez-vous à venir à un employé, une date et une heure, sans changer sa durée.
 *
 * Les règles de prise de rendez-vous de l'employé visé s'appliquent (voir AppointmentBooking). L'écriture est
 * sérialisée par employé et refusée si le rendez-vous a changé depuis son ouverture.
 */
class ReassignAppointment
{
    /**
     * @param  int  $version  Horodatage updated_at du rendez-vous à l'ouverture.
     *
     * @throws DomainException
     */
    public function handle(Appointment $appointment, int $userId, string $date, int $startMinute, int $version): Appointment
    {
        if (! Carbon::hasFormat($date, 'Y-m-d') || $startMinute % AppointmentBooking::SLOT_MINUTES !== 0) {
            throw new DomainException(__('Cette date ou cette heure n\'est pas valide.'));
        }

        if (Carbon::parse($appointment->date)->startOfDay()->addMinutes($appointment->start_minute)->lt(Carbon::now())) {
            throw new DomainException(__('Ce rendez-vous est déjà commencé ou passé.'));
        }

        $employee = User::query()->whereKey($userId)->where('is_active', true)->first();

        if (! $employee) {
            throw new DomainException(__('Cet employé n\'est pas actif.'));
        }

        if (! AppointmentBooking::canBookAt($userId, $date, $startMinute)) {
            $reason = AppointmentBooking::blockReasonFor($userId, $date);

            throw new DomainException($reason !== null
                ? __(':name n\'est pas disponible ce jour-là (:reason).', ['name' => $employee->fullName(), 'reason' => $reason])
                : __(':name ne peut pas prendre ce rendez-vous à cette heure : moment passé ou hors de son quart publié.', ['name' => $employee->fullName()]));
        }

        return DB::transaction(function () use ($appointment, $employee, $date, $startMinute, $version): Appointment {
            User::query()->whereKey($employee->id)->lockForUpdate()->first();

            $current = Appointment::query()->lockForUpdate()->find($appointment->id);

            if (! $current) {
                throw new DomainException(__('Ce rendez-vous a été supprimé par un autre usager.'));
            }

            if ($current->updated_at?->getTimestamp() !== $version) {
                throw new DomainException(__('Ce rendez-vous a été modifié entre-temps. Rouvrez-le pour voir les changements.'));
            }

            if ($current->duration_minutes > AppointmentBooking::maxDurationMinutes($employee->id, $date, $startMinute, $current->id)) {
                throw new DomainException(__('Ce rendez-vous chevauche un autre rendez-vous de :name ou dépasse son quart.', ['name' => $employee->fullName()]));
            }

            $current->update([
                'user_id' => $employee->id,
                'date' => $date,
                'start_minute' => $startMinute,
            ]);

            return $current;
        });
    }
}
