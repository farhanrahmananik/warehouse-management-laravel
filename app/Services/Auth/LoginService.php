<?php

namespace App\Services\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginService
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 60;

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * Attempt to authenticate the user and regenerate the session.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): void
    {
        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt($request->credentials(), $request->remember())) {
            RateLimiter::hit($request->throttleKey(), self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user instanceof User) {
            $this->auditLogService->record(
                event: 'login',
                module: 'auth',
                auditable: $user,
                description: 'User logged in.',
                metadata: [
                    'email' => (string) $request->string('email')->trim(),
                ],
                user: $user,
            );
        }
    }

    public function logout(Request $request): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->auditLogService->record(
                event: 'logout',
                module: 'auth',
                auditable: $user,
                description: 'User logged out.',
                user: $user,
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(LoginRequest $request): void
    {
        if (! RateLimiter::tooManyAttempts($request->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($request->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }
}
