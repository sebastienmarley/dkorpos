<?php

namespace App\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesPageRouteName
{
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
