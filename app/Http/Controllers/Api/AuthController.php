<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Staff login for the check-in app. Only admin/organizer accounts can
     * authenticate here — attendees use the website, not this app.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Auth::guard('web')->validate($credentials) || ! $user->canUseCheckinApp()) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials or this account is not authorized to use the check-in app.',
            ]);
        }

        $token = $user->createToken('checkin-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // Drives which screens the app offers. The API enforces the same
                // boundary on every finance route — this is for the UI, not the
                // guard, so a tampered client gains nothing.
                'can_manage_finance' => $user->canManageFinance(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }
}
