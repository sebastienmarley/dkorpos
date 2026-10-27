<?php

namespace App\Http\Middleware;

use App\Concerns\ResolvesPageRouteName;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsurePinIsSet
{
    use ResolvesPageRouteName;

    /**
     * Routes accessibles avant d'avoir créé son NIP : sa création, l'activation de la 2FA qui la précède, la
     * confirmation du mot de passe et la déconnexion.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['pin.edit', 'two-factor.*', 'password.confirm', 'password.confirm.store', 'password.confirmation', 'logout'];

    /**
     * Renvoie vers la création du NIP l'usager qui n'en a pas encore (ADR 0015). Placé après EnsureTwoFactorIsEnabled :
     * la 2FA obligatoire est activée en premier.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! config('access.pin.enforced')
            || $user === null
            || $user->pin !== null
            || Str::is(self::ALLOWED_ROUTES, $this->routeName($request))
        ) {
            return $next($request);
        }

        return redirect()->route('pin.edit');
    }
}
