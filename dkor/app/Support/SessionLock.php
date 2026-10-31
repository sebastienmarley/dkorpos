<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * État du verrouillage de la session après inactivité (ADR 0015). Gardé côté serveur : un nouvel onglet ou une page
 * rechargée reste verrouillé tant que le NIP n'est pas saisi.
 *
 * L'état est dans le cache plutôt que dans la session : chaque requête réécrit la session entière telle qu'elle l'a
 * lue, si bien qu'une requête Livewire en cours effaçait un battement de cœur, un verrouillage ou un déverrouillage
 * enregistré pendant ce temps. La session ne garde qu'un jeton qui ne change jamais, et qui sert de clé dans le cache.
 */
class SessionLock
{
    private const TOKEN_KEY = 'session_lock.token';

    /**
     * Seul un usager qui a un NIP peut déverrouiller sa session ; les autres sont d'abord envoyés le créer.
     */
    public function appliesTo(?User $user): bool
    {
        return $user !== null && $user->pin !== null;
    }

    public function isLocked(): bool
    {
        return (bool) Cache::get($this->cacheKey('locked'), false);
    }

    /**
     * Verrouille une session inactive depuis trop longtemps. Une session sans jeton est neuve : elle démarre à la
     * connexion, sauf si l'usager a été reconnecté par le témoin « Se souvenir de moi », qui ne doit pas contourner le
     * NIP sur un poste partagé. Une session qui a un jeton mais plus d'état a perdu son cache (vidé au déploiement, par
     * exemple) : elle est verrouillée plutôt que rouverte.
     */
    public function lockIfIdle(): void
    {
        $isKnownSession = is_string(session()->get(self::TOKEN_KEY));

        if ($this->isLocked()) {
            return;
        }

        $lastActivityAt = Cache::get($this->cacheKey('last_activity_at'));

        if ($lastActivityAt === null) {
            $isKnownSession || Auth::viaRemember() ? $this->lock() : $this->touch();

            return;
        }

        if (now()->timestamp - $lastActivityAt >= $this->idleSeconds()) {
            $this->lock();
        }
    }

    public function lock(): void
    {
        $this->remember('locked', true);
    }

    public function unlock(): void
    {
        $this->remember('locked', false);
        $this->touch();
    }

    public function touch(): void
    {
        $this->remember('last_activity_at', now()->getTimestamp());
    }

    public function idleSeconds(): int
    {
        return config('access.session_lock.idle_minutes') * 60;
    }

    /**
     * Gardé aussi longtemps que la session elle-même : au-delà, la session a expiré et l'usager doit se reconnecter.
     */
    private function remember(string $name, bool|int $value): void
    {
        Cache::put($this->cacheKey($name), $value, now()->addMinutes((int) config('session.lifetime')));
    }

    private function cacheKey(string $name): string
    {
        $token = session()->get(self::TOKEN_KEY);

        if (! is_string($token)) {
            $token = Str::random(40);
            session()->put(self::TOKEN_KEY, $token);
        }

        return "session-lock:{$token}:{$name}";
    }
}
