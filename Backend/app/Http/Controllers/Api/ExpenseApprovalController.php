<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExpenseClaimStatus;
use App\Http\Controllers\Controller;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Services\Expenses\ExpenseClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseApprovalController extends Controller
{
    public function __construct(
        private readonly ExpenseClaimService $expenses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'pending');
        $query = match ($status) {
            'pending' => $this->expenses->queueQuery($request->user()),
            'approved' => $this->expenses->visibleQuery($request->user())->where('status', ExpenseClaimStatus::Approved->value),
            'rejected' => $this->expenses->visibleQuery($request->user())->where('status', ExpenseClaimStatus::Rejected->value),
            'paid', 'payment' => $this->expenses->visibleQuery($request->user())->where('payment_status', 'paid'),
            default => $this->expenses->visibleQuery($request->user())->where('status', '!=', ExpenseClaimStatus::Draft->value),
        };

        // A selected center must not hide a claim that is already waiting for this approver.
        if ($status !== 'pending' && $request->filled('center_id')) {
            $query->where('center_id', (int) $request->query('center_id'));
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->query('employee_id'));
        }
        if ($request->filled('type')) {
            $query->whereHas('items', fn ($items) => $items->where('type', $request->query('type')));
        }
        if ($request->filled('from')) {
            $query->whereHas('items', fn ($items) => $items->whereDate('expense_date', '>=', $request->query('from')));
        }
        if ($request->filled('to')) {
            $query->whereHas('items', fn ($items) => $items->whereDate('expense_date', '<=', $request->query('to')));
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        $claims = $query
            ->with($this->expenses->relations())
            ->latest('id')
            ->get()
            ->map(fn (ExpenseClaim $claim): array => $this->expenses->present($claim))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Expenses loaded.',
            'pending_count' => $this->expenses->pendingCountFor($request->user()),
            'data' => $claims,
        ]);
    }

    public function receipt(Request $request, ExpenseClaim $expenseClaim, ExpenseItem $item): StreamedResponse
    {
        $this->expenses->assertCanView($request->user(), $expenseClaim);
        abort_unless((int) $item->expense_claim_id === (int) $expenseClaim->id && $item->hasReceipt(), 404);

        return Storage::disk($item->receipt_disk)->response(
            $item->receipt_path,
            $item->receipt_original_name ?: 'receipt',
            ['Content-Type' => $item->receipt_mime ?: 'application/octet-stream'],
        );
    }

    public function show(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $this->expenses->assertCanView($request->user(), $expenseClaim);

        return response()->json([
            'success' => true,
            'data' => $this->expenses->present($expenseClaim->load($this->expenses->relations())),
        ]);
    }

    public function approve(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $claim = $this->expenses->approve($request->user(), $expenseClaim, $request->input('remarks'));

        return response()->json([
            'success' => true,
            'message' => 'Expense approved.',
            'data' => $this->expenses->present($claim),
        ]);
    }

    public function reject(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);
        $claim = $this->expenses->reject($request->user(), $expenseClaim, $data['remarks']);

        return response()->json([
            'success' => true,
            'message' => 'Expense rejected.',
            'data' => $this->expenses->present($claim),
        ]);
    }

    public function pay(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'string', 'in:cash,upi,bank_transfer,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $claim = $this->expenses->markPaid($request->user(), $expenseClaim, $data);

        return response()->json([
            'success' => true,
            'message' => 'Expense marked paid.',
            'data' => $this->expenses->present($claim),
        ]);
    }
}
