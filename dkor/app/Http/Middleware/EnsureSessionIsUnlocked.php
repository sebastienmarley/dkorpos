<?php

namespace App\Http\Middleware;

use App\Concerns\ResolvesPageRouteName;
use App\Support\SessionLock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsUnlocked
{
    use ResolvesPageRouteName;

    /**
     * Routes accessibles pendant le verrouillage : l'écran de verrouillage, son déverrouillage et le changement
     * d'usager.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['session-lock.*', 'logout'];

    public function __construct(private SessionLock $sessionLock) {}

    /**
     * Refuse toute requête d'une session verrouillée (ADR 0015) : le verrou ne repose pas sur l'écran affiché par le
     * navigateur. Une requête Livewire reçoit un 423, que la page intercepte pour afficher l'écran sans perdre son
     * état ; une page est redirigée vers l'écran de verrouillage.
     *
     * Seules les pages comptent comme activité : un sondage wire:poll ne doit pas garder la session ouverte. L'activité
     * sur une page déjà chargée est signalée par la route session-lock.heartbeat.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->sessionLock->appliesTo($request->user())) {
            return $next($request);
        }

        $this->sessionLock->lockIfIdle();

        if (Str::is(self::ALLOWED_ROUTES, $this->routeName($request))) {
            return $next($request);
        }

        if ($this->sessionLock->isLocked()) {
            if (Livewire::isLivewireRequest() || $request->expectsJson()) {
                return response()->json(['locked' => true], Response::HTTP_LOCKED);
            }

            if ($request->isMethod('GET')) {
                redirect()->setIntendedUrl($request->fullUrl());
            }

            return redirect()->route('session-lock.show');
        }

        if (Livewire::isLivewireRequest()) {
            return $next($request);
        }

        $this->sessionLock->touch();

        return $this->preventHistoryCache($next($request));
    }

    /**
     * Le bouton Précédent réaffiche une page du cache sans la redemander au serveur, même avec no-cache : no-store
     * l'en empêche, pour qu'une session verrouillée ne réaffiche pas une page déverrouillée.
     */
    private function preventHistoryCache(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
