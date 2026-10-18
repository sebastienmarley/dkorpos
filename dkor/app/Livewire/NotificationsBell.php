<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Livewire\Component;

/**
 * Cloche des notifications non lues de l'usager. Chaque notification fournit title, body, url et icon (voir LabelsToPrint).
 */
class NotificationsBell extends Component
{
    public function open(string $notificationId): void
    {
        /** @var DatabaseNotification $notification */
        $notification = auth()->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if (is_string($url)) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    /** @return DatabaseNotificationCollection<int, DatabaseNotification> */
    public function getNotifications(): DatabaseNotificationCollection
    {
        return auth()->user()->unreadNotifications()->latest()->limit(10)->get();
    }

    public function render(): View
    {
        return view('livewire.notifications-bell', [
            'notifications' => $this->getNotifications(),
        ]);
    }
}
