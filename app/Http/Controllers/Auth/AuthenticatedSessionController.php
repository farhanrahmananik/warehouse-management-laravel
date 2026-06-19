<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, LoginService $loginService): RedirectResponse
    {
        $loginService->login($request);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, LoginService $loginService): RedirectResponse
    {
        $loginService->logout($request);

        return redirect()->route('login');
    }
}
