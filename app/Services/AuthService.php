<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
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

        $token = $user->createToken('auth-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

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

        $token = $user->createToken('google-auth-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
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
