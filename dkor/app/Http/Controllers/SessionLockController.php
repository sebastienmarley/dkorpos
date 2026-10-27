<?php

namespace App\Http\Controllers;

use App\Support\SessionLock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Verrouillage de la session après inactivité et déverrouillage par NIP (ADR 0015). Les routes répondent en JSON à
 * l'écran superposé à la page, et par une redirection à l'écran pleine page qu'ouvre un nouvel onglet.
 */
class SessionLockController extends Controller
{
    public function __construct(private SessionLock $sessionLock) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->sessionLock->isLocked()) {
            return redirect()->intended(route('home'));
        }

        return view('session-lock', [
            'title' => __('Session verrouillée'),
            'user' => $request->user(),
        ]);
    }

    /**
     * Verrouille tout de suite : délai d'inactivité atteint dans le navigateur, ou « Verrouiller » choisi par l'usager.
     */
    public function lock(Request $request): JsonResponse|RedirectResponse
    {
        $this->sessionLock->lock();

        return $request->expectsJson()
            ? response()->json(['locked' => true])
            : redirect()->route('session-lock.show');
    }

    /**
     * Activité de l'usager sur une page déjà chargée. N'ouvre pas une session déjà verrouillée.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        if (! $this->sessionLock->isLocked()) {
            $this->sessionLock->touch();
        }

        return response()->json(['locked' => $this->sessionLock->isLocked()]);
    }

    /**
     * Les essais sont comptés sur toute la durée de la session, pour qu'on ne puisse pas deviner le NIP en espaçant
     * les essais. Après le dernier essai permis, l'usager est déconnecté.
     */
    public function unlock(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['pin' => ['required', 'string']], ['pin.required' => __('Entrez votre NIP.')]);

        $user = $request->user();
        $throttleKey = 'session-unlock:'.$user->id;

        if (Hash::check($request->string('pin'), $user->pin)) {
            RateLimiter::clear($throttleKey);
            $this->sessionLock->unlock();

            return $request->expectsJson()
                ? response()->json(['locked' => false])
                : redirect()->intended(route('home'));
        }

        RateLimiter::hit($throttleKey, config('session.lifetime') * 60);
        $remainingAttempts = config('access.session_lock.max_attempts') - RateLimiter::attempts($throttleKey);

        if ($remainingAttempts > 0) {
            throw ValidationException::withMessages([
                'pin' => trans_choice('NIP incorrect. Il reste :count essai.|NIP incorrect. Il reste :count essais.', $remainingAttempts),
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $status = __('Trop d\'essais de NIP. Reconnectez-vous avec votre mot de passe.');

        return $request->expectsJson()
            ? response()->json(['redirect' => route('login'), 'message' => $status], 401)
            : redirect()->route('login')->with('status', $status);
    }
}
