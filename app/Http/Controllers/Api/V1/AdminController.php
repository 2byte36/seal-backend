<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LeaveBalanceResource;
use App\Http\Resources\V1\UserResource;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Dashboard statistics for admin.
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_employees' => User::where('role', 'employee')->count(),
            'pending_requests' => LeaveRequest::where('status', 'pending')->count(),
            'approved_this_month' => LeaveRequest::where('status', 'approved')
                ->whereMonth('reviewed_at', now()->month)
                ->whereYear('reviewed_at', now()->year)
                ->count(),
            'rejected_this_month' => LeaveRequest::where('status', 'rejected')
                ->whereMonth('reviewed_at', now()->month)
                ->whereYear('reviewed_at', now()->year)
                ->count(),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * List all employees with their leave balances.
     */
    public function employees(Request $request): JsonResponse
    {
        $employees = User::where('role', 'employee')
            ->with('leaveBalance')
            ->paginate($request->input('per_page', 15));

        $data = $employees->getCollection()->map(function (User $user) {
            return [
                'user' => new UserResource($user),
                'balance' => $user->leaveBalance
                    ? new LeaveBalanceResource($user->leaveBalance)
                    : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    /**
     * View an employee's leave balance for a given year.
     */
    public function employeeBalance(User $user, Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);

        $balance = LeaveBalance::firstOrCreate(
            ['user_id' => $user->id, 'year' => $year],
            [
                'total_quota' => (int) config('app.leave_quota_per_year', 12),
                'used' => 0,
                'pending' => 0,
            ]
        );

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'balance' => new LeaveBalanceResource($balance),
            ],
        ]);
    }
}
