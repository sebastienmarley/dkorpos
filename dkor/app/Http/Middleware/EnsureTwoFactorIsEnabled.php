<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureTwoFactorIsEnabled
{
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

    /**
     * Pour une requête Livewire, le nom de la route de la page qui contient le composant.
     */
    private function routeName(Request $request): string
    {
        if (! Livewire::isLivewireRequest()) {
            return (string) $request->route()?->getName();
        }

        try {
            return (string) Route::getRoutes()->match(Request::create('/'.ltrim(Livewire::originalPath(), '/')))->getName();
        } catch (HttpException) {
            return '';
        }
    }
}
