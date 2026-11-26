<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * One-click sign-in for customer demos. Signs in the configured demo account
 * without a password, so it only exists when DEMO_LOGIN=true. Never turn it
 * on for a customer's live deployment.
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('iproduction.demo_login.enabled'), 404);

        $user = User::query()
            ->where('email', config('iproduction.demo_login.email'))
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return back()->withErrors(['login' => 'The demo account is not available.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        activity()->causedBy($user)->performedOn($user)->log('Signed in with the demo login');

        return redirect()->intended(route('dashboard'));
    }
}
