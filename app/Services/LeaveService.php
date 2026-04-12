<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceLedger;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /**
     * Calculate business days (exclude weekends) between two dates.
     */
    public function calculateBusinessDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if (! $current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Check if the user has overlapping leave requests.
     */
    public function hasOverlappingLeave(User $user, Carbon $startDate, Carbon $endDate, ?int $excludeId = null): bool
    {
        $query = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($inner) use ($startDate, $endDate) {
                    $inner->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get or create leave balance for user in given year.
     */
    public function getOrCreateBalance(User $user, int $year): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            ['user_id' => $user->id, 'year' => $year],
            [
                'total_quota' => (int) config('app.leave_quota_per_year', 12),
                'used' => 0,
                'pending' => 0,
            ]
        );
    }

    /**
     * Submit a new leave request.
     */
    public function submitLeave(User $user, array $data, UploadedFile $attachment): LeaveRequest
    {
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $totalDays = $this->calculateBusinessDays($startDate, $endDate);

        if ($totalDays === 0) {
            throw ValidationException::withMessages([
                'start_date' => ['The selected dates contain no business days.'],
            ]);
        }

        if ($this->hasOverlappingLeave($user, $startDate, $endDate)) {
            throw ValidationException::withMessages([
                'start_date' => ['You already have a leave request overlapping with these dates.'],
            ]);
        }

        $balance = $this->getOrCreateBalance($user, $startDate->year);
        $remaining = $balance->remaining();

        if ($totalDays > $remaining) {
            throw ValidationException::withMessages([
                'start_date' => ["Insufficient leave balance. You have {$remaining} day(s) remaining but requested {$totalDays} day(s)."],
            ]);
        }

        return DB::transaction(function () use ($user, $data, $attachment, $startDate, $endDate, $totalDays, $balance) {
            $path = $attachment->store('leave-attachments', 'local');

            $leaveRequest = LeaveRequest::create([
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'reason' => $data['reason'],
                'attachment_path' => $path,
                'attachment_original_name' => $attachment->getClientOriginalName(),
                'status' => 'pending',
            ]);

            $balance->increment('pending', $totalDays);

            LeaveBalanceLedger::create([
                'user_id' => $user->id,
                'leave_request_id' => $leaveRequest->id,
                'type' => 'deduction',
                'amount' => -$totalDays,
                'balance_after' => $balance->total_quota - $balance->used - $balance->pending,
                'description' => "Leave request #{$leaveRequest->id} submitted ({$totalDays} business days)",
                'year' => $startDate->year,
            ]);

            return $leaveRequest;
        });
    }

    /**
     * Approve a leave request (Admin only).
     */
    public function approveLeave(LeaveRequest $leaveRequest, User $admin, ?string $note = null): LeaveRequest
    {
        if (! $leaveRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['This leave request has already been reviewed.'],
            ]);
        }

        return DB::transaction(function () use ($leaveRequest, $admin, $note) {
            $leaveRequest->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'review_note' => $note,
                'reviewed_at' => now(),
            ]);

            $balance = $this->getOrCreateBalance($leaveRequest->user, $leaveRequest->start_date->year);
            $balance->decrement('pending', $leaveRequest->total_days);
            $balance->increment('used', $leaveRequest->total_days);

            LeaveBalanceLedger::create([
                'user_id' => $leaveRequest->user_id,
                'leave_request_id' => $leaveRequest->id,
                'type' => 'deduction',
                'amount' => 0,
                'balance_after' => $balance->total_quota - $balance->used - $balance->pending,
                'description' => "Leave request #{$leaveRequest->id} approved by {$admin->name}",
                'year' => $leaveRequest->start_date->year,
            ]);

            return $leaveRequest->fresh();
        });
    }

    /**
     * Reject a leave request (Admin only).
     */
    public function rejectLeave(LeaveRequest $leaveRequest, User $admin, ?string $note = null): LeaveRequest
    {
        if (! $leaveRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['This leave request has already been reviewed.'],
            ]);
        }

        return DB::transaction(function () use ($leaveRequest, $admin, $note) {
            $leaveRequest->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'review_note' => $note,
                'reviewed_at' => now(),
            ]);

            $balance = $this->getOrCreateBalance($leaveRequest->user, $leaveRequest->start_date->year);
            $balance->decrement('pending', $leaveRequest->total_days);

            LeaveBalanceLedger::create([
                'user_id' => $leaveRequest->user_id,
                'leave_request_id' => $leaveRequest->id,
                'type' => 'reversal',
                'amount' => $leaveRequest->total_days,
                'balance_after' => $balance->total_quota - $balance->used - $balance->pending,
                'description' => "Leave request #{$leaveRequest->id} rejected - {$leaveRequest->total_days} day(s) restored",
                'year' => $leaveRequest->start_date->year,
            ]);

            return $leaveRequest->fresh();
        });
    }
}
