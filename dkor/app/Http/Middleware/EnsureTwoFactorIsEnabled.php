<?php

namespace App\Http\Middleware;

use App\Concerns\ResolvesPageRouteName;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsEnabled
{
    use ResolvesPageRouteName;

    /**
     * Routes accessibles avant d'avoir activé la 2FA : son réglage, la confirmation du mot de passe et la déconnexion.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['two-factor.*', 'password.confirm', 'password.confirm.store', 'password.confirmation', 'logout'];

    /**
     * Renvoie vers l'activation de la 2FA l'usager pour qui elle est obligatoire et qui ne l'a pas encore (ADR 0014).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! config('access.two_factor.enforced')
            || $user === null
            || $user->hasEnabledTwoFactorAuthentication()
            || ! $user->requiresTwoFactor()
            || Str::is(self::ALLOWED_ROUTES, $this->routeName($request))
        ) {
            return $next($request);
        }

        return redirect()->route('two-factor.edit');
    }
}
