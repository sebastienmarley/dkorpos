<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * État du verrouillage de la session après inactivité (ADR 0015). Gardé en session côté serveur : un nouvel onglet ou
 * une page rechargée reste verrouillé tant que le NIP n'est pas saisi.
 */
class SessionLock
{
    private const LAST_ACTIVITY_KEY = 'session_lock.last_activity_at';

    private const LOCKED_KEY = 'session_lock.locked';

    /**
     * Seul un usager qui a un NIP peut déverrouiller sa session ; les autres sont d'abord envoyés le créer.
     */
    public function appliesTo(?User $user): bool
    {
        return $user !== null && $user->pin !== null;
    }

    public function isLocked(): bool
    {
        return (bool) session()->get(self::LOCKED_KEY, false);
    }

    /**
     * Verrouille une session inactive depuis trop longtemps. Une session sans activité connue est neuve : elle démarre
     * à la connexion, sauf si l'usager a été reconnecté par le témoin « Se souvenir de moi », qui ne doit pas
     * contourner le NIP sur un poste partagé.
     */
    public function lockIfIdle(): void
    {
        if ($this->isLocked()) {
            return;
        }

        $lastActivityAt = session()->get(self::LAST_ACTIVITY_KEY);

        if ($lastActivityAt === null) {
            Auth::viaRemember() ? $this->lock() : $this->touch();

            return;
        }

        if (now()->timestamp - $lastActivityAt >= $this->idleSeconds()) {
            $this->lock();
        }
    }

    public function lock(): void
    {
        session()->put(self::LOCKED_KEY, true);
    }

    public function unlock(): void
    {
        session()->put(self::LOCKED_KEY, false);
        $this->touch();
    }

    public function touch(): void
    {
        session()->put(self::LAST_ACTIVITY_KEY, now()->timestamp);
    }

    public function idleSeconds(): int
    {
        return config('access.session_lock.idle_minutes') * 60;
    }
}
