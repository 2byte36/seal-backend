<?php

namespace Database\Seeders;

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceLedger;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Create Admin ────────────────────────────────────────────
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        LeaveBalance::create([
            'user_id' => $admin->id,
            'year' => now()->year,
            'total_quota' => 12,
            'used' => 0,
            'pending' => 0,
        ]);

        // ── Create Employees ────────────────────────────────────────
        $employees = [];

        $employeeData = [
            ['name' => 'Budi Santoso', 'email' => 'budi@example.com'],
            ['name' => 'Siti Rahayu', 'email' => 'siti@example.com'],
            ['name' => 'Ahmad Rizki', 'email' => 'ahmad@example.com'],
            ['name' => 'Dewi Lestari', 'email' => 'dewi@example.com'],
            ['name' => 'Eko Prasetyo', 'email' => 'eko@example.com'],
        ];

        foreach ($employeeData as $data) {
            $employee = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'role' => 'employee',
                'email_verified_at' => now(),
            ]);

            $balance = LeaveBalance::create([
                'user_id' => $employee->id,
                'year' => now()->year,
                'total_quota' => 12,
                'used' => 0,
                'pending' => 0,
            ]);

            LeaveBalanceLedger::create([
                'user_id' => $employee->id,
                'leave_request_id' => null,
                'type' => 'allocation',
                'amount' => 12,
                'balance_after' => 12,
                'description' => 'Annual leave quota allocation for '.now()->year,
                'year' => now()->year,
            ]);

            $employees[] = ['user' => $employee, 'balance' => $balance];
        }

        // ── Create Sample Leave Requests ────────────────────────────

        // Budi: approved leave
        $leave1 = LeaveRequest::create([
            'user_id' => $employees[0]['user']->id,
            'start_date' => Carbon::now()->addDays(5)->startOfWeek(),
            'end_date' => Carbon::now()->addDays(5)->startOfWeek()->addDays(2),
            'total_days' => 3,
            'reason' => 'Family vacation trip to Bali',
            'attachment_path' => 'leave-attachments/sample.pdf',
            'attachment_original_name' => 'booking_confirmation.pdf',
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'review_note' => 'Approved. Enjoy your vacation!',
            'reviewed_at' => now(),
        ]);
        $employees[0]['balance']->update(['used' => 3]);

        // Siti: pending leave
        $leave2 = LeaveRequest::create([
            'user_id' => $employees[1]['user']->id,
            'start_date' => Carbon::now()->addDays(14)->startOfWeek(),
            'end_date' => Carbon::now()->addDays(14)->startOfWeek()->addDays(4),
            'total_days' => 5,
            'reason' => 'Medical procedure and recovery time',
            'attachment_path' => 'leave-attachments/sample.pdf',
            'attachment_original_name' => 'medical_letter.pdf',
            'status' => 'pending',
        ]);
        $employees[1]['balance']->update(['pending' => 5]);

        // Ahmad: rejected leave
        $leave3 = LeaveRequest::create([
            'user_id' => $employees[2]['user']->id,
            'start_date' => Carbon::now()->addDays(3),
            'end_date' => Carbon::now()->addDays(3),
            'total_days' => 1,
            'reason' => 'Personal errand',
            'attachment_path' => 'leave-attachments/sample.pdf',
            'attachment_original_name' => 'personal_doc.jpg',
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'review_note' => 'Rejected due to project deadline. Please reschedule.',
            'reviewed_at' => now(),
        ]);

        // Dewi: pending leave
        LeaveRequest::create([
            'user_id' => $employees[3]['user']->id,
            'start_date' => Carbon::now()->addDays(21)->startOfWeek(),
            'end_date' => Carbon::now()->addDays(21)->startOfWeek()->addDays(1),
            'total_days' => 2,
            'reason' => 'Wedding ceremony attendance',
            'attachment_path' => 'leave-attachments/sample.pdf',
            'attachment_original_name' => 'invitation.png',
            'status' => 'pending',
        ]);
        $employees[3]['balance']->update(['pending' => 2]);
    }
}
