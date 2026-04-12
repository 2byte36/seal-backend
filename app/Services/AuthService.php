<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'employee',
        ]);

        $this->ensureLeaveBalance($user);

        $token = Auth::guard('api')->login($user);

        return ['user' => $user, 'token' => $token];
    }

    public function login(string $email, string $password): array
    {
        $token = Auth::guard('api')->attempt([
            'email' => $email,
            'password' => $password,
        ]);

        if (! $token) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::guard('api')->user();

        return ['user' => $user, 'token' => $token];
    }

    public function handleGoogleCallback(object $googleUser): array
    {
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);
        } else {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'role' => 'employee',
            ]);
        }

        $this->ensureLeaveBalance($user);

        $token = Auth::guard('api')->login($user);

        return ['user' => $user, 'token' => $token];
    }

    public function logout(): void
    {
        Auth::guard('api')->logout();
    }

    public function refresh(): string
    {
        return Auth::guard('api')->refresh();
    }

    private function ensureLeaveBalance(User $user): void
    {
        $user->leaveBalances()->firstOrCreate(
            ['year' => now()->year],
            [
                'total_quota' => (int) config('app.leave_quota_per_year', 12),
                'used' => 0,
                'pending' => 0,
            ]
        );
    }
}
