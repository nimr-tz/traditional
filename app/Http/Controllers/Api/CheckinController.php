<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckinController extends Controller
{
    /**
     * Register a walk-in attendee and make them immediately available for
     * attendance. This staff-only flow intentionally excludes billing.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'institution' => ['nullable', 'string', 'max:255'],
            'participant_type' => ['required', Rule::in(array_keys(config('tmsc.participant_types')))],
        ]);

        $user = new User([
            ...$validated,
            'password' => Str::password(32),
        ]);
        $user->email_verified_at = now();
        $user->payment_status = 'verified';
        $user->paid_at = now();
        $user->generateRegistrationCode();
        $user->save();

        return response()->json([
            'message' => 'Attendee registered.',
            'user' => $user->only([
                'id', 'name', 'email', 'phone', 'institution', 'participant_type', 'registration_code',
            ]),
        ], 201);
    }

    /**
     * Scan a badge QR code (its registration_code) and check the attendee in.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $user = User::where('registration_code', $request->code)->first();

        if (! $user) {
            return response()->json(['message' => 'No registrant found for this code.'], 404);
        }

        return $this->checkIn($user, $request);
    }

    /**
     * Fallback for lost/unscannable badges — look up by name or email.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);

        $users = User::where('is_admin', false)
            ->where('payment_status', 'verified')
            ->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->q}%")
                    ->orWhere('email', 'like', "%{$request->q}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'email', 'institution', 'participant_type', 'registration_code']);

        return response()->json(['results' => $users]);
    }

    /**
     * Manually check in a user found via lookup() rather than a QR scan.
     */
    public function checkInById(Request $request, User $user): JsonResponse
    {
        return $this->checkIn($user, $request);
    }

    public function recent(): JsonResponse
    {
        $attendances = Attendance::with(['user:id,name,institution', 'staff:id,name'])
            ->latest('checked_in_at')
            ->limit(25)
            ->get();

        return response()->json(['attendances' => $attendances]);
    }

    private function checkIn(User $user, Request $request): JsonResponse
    {
        if (! $user->isPaid()) {
            return response()->json([
                'message' => "{$user->name}'s payment has not been verified yet — cannot check in.",
            ], 422);
        }

        $existing = $user->attendance()->first();

        if ($existing) {
            return response()->json([
                'already_checked_in' => true,
                'checked_in_at' => $existing->checked_in_at,
                'user' => $user->only(['id', 'name', 'institution', 'participant_type']),
            ]);
        }

        $attendance = $user->attendance()->create([
            'checked_in_at' => now(),
            'checked_in_by' => $request->user()->id,
        ]);

        return response()->json([
            'already_checked_in' => false,
            'checked_in_at' => $attendance->checked_in_at,
            'user' => $user->only(['id', 'name', 'institution', 'participant_type']),
        ]);
    }
}
