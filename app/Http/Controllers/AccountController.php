<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function change_status(Request $request) {

        $validated = $request->validate([
            'id' => ['required', 'exists:users,id'],
            'action' => ['required', 'in:active,inactive'],
        ]);

        $user = User::findOrFail($validated['id']);

        $user->update([
            'account_status' => $validated['action'],
        ]);

        return redirect()->back()->with('success', "Account has been {$validated['action']}.");
    }

    public function update_password(Request $request){
        $validated = $request->validate([
            'id' => ['required','exists:users,id'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::findOrFail($validated['id']);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success','Password has been updated successfully.');
    }
}
