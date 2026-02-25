<?php

namespace App\Filament\Auth;

use App\Support\AdminStartPage;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        return redirect()->to(AdminStartPage::resolveForCurrentUser());
    }
}
