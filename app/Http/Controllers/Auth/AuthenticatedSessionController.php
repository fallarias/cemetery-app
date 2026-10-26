<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\{Hash, RateLimiter};
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'users' => User::where('account_status', 'active')
                ->orderBy('name')
                ->get()
                ->map(fn ($u) => [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'avatar_url' => $u->avatar_url,
                ]),
        ]);
    }
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // ✅ Block Pending users after authentication
        $user = Auth::user();
        if ($user->account_status === 'inactive') {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Your account is still pending admin approval.',
            ]);
        }

        return redirect()->intended(route('employee.display_employee', absolute: false));
    }

    public function quick_login(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($data['user_id']);

        abort_unless($user->account_status === 'active', 403);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('employee.display_employee', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
