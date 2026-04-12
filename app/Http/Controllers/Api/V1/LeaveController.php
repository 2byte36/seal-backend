<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ReviewLeaveRequest;
use App\Http\Requests\V1\StoreLeaveRequest;
use App\Http\Resources\V1\LeaveBalanceLedgerResource;
use App\Http\Resources\V1\LeaveBalanceResource;
use App\Http\Resources\V1\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller
{
    public function __construct(
        private LeaveService $leaveService
    ) {}

    /**
     * Employee: list own leave requests.
     * Admin: list all leave requests with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = LeaveRequest::with(['user', 'reviewer']);

        if ($user->isEmployee()) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('user_id') && $user->isAdmin()) {
            $query->where('user_id', $request->input('user_id'));
        }

        $leaves = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => LeaveRequestResource::collection($leaves),
            'meta' => [
                'current_page' => $leaves->currentPage(),
                'last_page' => $leaves->lastPage(),
                'per_page' => $leaves->perPage(),
                'total' => $leaves->total(),
            ],
        ]);
    }

    /**
     * Show a single leave request.
     */
    public function show(Request $request, LeaveRequest $leave): JsonResponse
    {
        $user = $request->user();

        if ($user->isEmployee() && $leave->user_id !== $user->id) {
            return response()->json([
                'message' => 'You do not have permission to view this leave request.',
            ], 403);
        }

        $leave->load(['user', 'reviewer']);

        return response()->json([
            'data' => new LeaveRequestResource($leave),
        ]);
    }

    /**
     * Submit a new leave request (Employee only).
     */
    public function store(StoreLeaveRequest $request): JsonResponse
    {
        $leaveRequest = $this->leaveService->submitLeave(
            $request->user(),
            $request->validated(),
            $request->file('attachment')
        );

        $leaveRequest->load(['user']);

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'data' => new LeaveRequestResource($leaveRequest),
        ], 201);
    }

    /**
     * Download leave attachment.
     */
    public function downloadAttachment(Request $request, LeaveRequest $leave)
    {
        $user = $request->user();

        if ($user->isEmployee() && $leave->user_id !== $user->id) {
            return response()->json([
                'message' => 'You do not have permission to access this attachment.',
            ], 403);
        }

        if (! Storage::disk('local')->exists($leave->attachment_path)) {
            return response()->json([
                'message' => 'Attachment file not found.',
            ], 404);
        }

        return Storage::disk('local')->download(
            $leave->attachment_path,
            $leave->attachment_original_name
        );
    }

    /**
     * Approve or Reject a leave request (Admin only).
     */
    public function review(ReviewLeaveRequest $request, LeaveRequest $leave): JsonResponse
    {
        $admin = $request->user();
        $status = $request->validated('status');
        $note = $request->validated('review_note');

        $leaveRequest = match ($status) {
            'approved' => $this->leaveService->approveLeave($leave, $admin, $note),
            'rejected' => $this->leaveService->rejectLeave($leave, $admin, $note),
        };

        $leaveRequest->load(['user', 'reviewer']);

        return response()->json([
            'message' => "Leave request has been {$status}.",
            'data' => new LeaveRequestResource($leaveRequest),
        ]);
    }

    /**
     * Get current user's leave balance.
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = $request->input('year', now()->year);

        $balance = $this->leaveService->getOrCreateBalance($user, (int) $year);

        return response()->json([
            'data' => new LeaveBalanceResource($balance),
        ]);
    }

    /**
     * Get balance ledger history (transaction log).
     */
    public function ledger(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = $request->input('year', now()->year);

        $ledger = $user->leaveBalanceLedgers()
            ->where('year', $year)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => LeaveBalanceLedgerResource::collection($ledger),
            'meta' => [
                'current_page' => $ledger->currentPage(),
                'last_page' => $ledger->lastPage(),
                'per_page' => $ledger->perPage(),
                'total' => $ledger->total(),
            ],
        ]);
    }
}
