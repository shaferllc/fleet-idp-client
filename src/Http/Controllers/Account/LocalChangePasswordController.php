<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Controllers\Account;

use Fleet\IdpClient\Support\FleetPasswordRouting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

final class LocalChangePasswordController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (FleetPasswordRouting::userPasswordManagedByIdp($request->user())) {
            return redirect()->away(FleetPasswordRouting::idpChangePasswordUrl());
        }

        return view('fleet-idp::account.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        if (FleetPasswordRouting::userPasswordManagedByIdp($request->user())) {
            return redirect()->away(FleetPasswordRouting::idpChangePasswordUrl());
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('status', trans('fleet-idp::account.password_updated'));
    }
}
