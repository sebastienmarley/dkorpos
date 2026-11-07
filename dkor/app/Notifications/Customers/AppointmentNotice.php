<?php

namespace App\Notifications\Customers;

use App\Enums\CustomerNoticeType;
use App\Models\Appointment;
use App\Models\customer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avis au client sur son rendez-vous (issue #138) : confirmé, déplacé, annulé ou rappel de la veille.
 */
class AppointmentNotice extends Notification
{
    public function __construct(public Appointment $appointment, public CustomerNoticeType $type) {}

    /** @return list<string> */
    public function via(customer $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(customer $notifiable): MailMessage
    {
        $store = $this->appointment->store();
        $when = $this->appointment->startsAtLabel();

        $message = (new MailMessage)
            ->subject($this->type->label().($store ? ' — '.$store->name : ''))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->firstname]));

        if (filled($store?->email)) {
            $message->replyTo($store->email, $store->name);
        }

        match ($this->type) {
            CustomerNoticeType::AppointmentMoved => $message->line(__('Votre rendez-vous est déplacé au :when.', ['when' => $when])),
            CustomerNoticeType::AppointmentCancelled => $message
                ->line(__('Votre rendez-vous du :when est annulé.', ['when' => $when]))
                ->line(__('Communiquez avec nous pour en prendre un autre.')),
            CustomerNoticeType::AppointmentReminder => $message->line(__('Petit rappel : votre rendez-vous est demain, le :when.', ['when' => $when])),
            default => $message->line(__('Votre rendez-vous est confirmé le :when.', ['when' => $when])),
        };

        if ($store !== null && $this->type !== CustomerNoticeType::AppointmentCancelled) {
            $address = $store->addressLine();

            $message->line(__('Lieu : :store', ['store' => $store->name]))
                ->lineIf(filled($address), __('Adresse : :address', ['address' => $address]));
        }

        if (filled($store?->phone)) {
            $message->line(__('Pour changer ou annuler votre rendez-vous, communiquez avec nous au :phone.', ['phone' => $store->phone]));
        }

        return $message->salutation(__('Au plaisir de vous voir!'));
    }
}
