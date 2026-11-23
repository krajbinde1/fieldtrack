<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Services\Expenses\ExpenseClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseClaimService $expenses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $claims = ExpenseClaim::query()
            ->with($this->expenses->relations())
            ->where('created_by_user_id', $request->user()->id)
            ->latest('id')
            ->get()
            ->map(fn (ExpenseClaim $claim): array => $this->expenses->present($claim))
            ->values();

        return $this->ok('Expenses loaded.', [
            'summary' => $this->expenses->summaryFor($request->user()),
            'claims' => $claims,
        ]);
    }

    public function show(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $this->expenses->assertCanView($request->user(), $expenseClaim);

        return $this->ok('Expense loaded.', $this->expenses->present($expenseClaim->load($this->expenses->relations())));
    }

    public function store(Request $request): JsonResponse
    {
        $submit = $request->input('action') === 'submit';
        $claim = $this->expenses->save(
            $request->user(),
            $this->items($request),
            $this->receipts($request),
            $submit,
        );

        return $this->ok($submit ? 'Expense submitted.' : 'Draft saved.', $this->expenses->present($claim), 201);
    }

    public function update(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $submit = $request->input('action') === 'submit';
        $claim = $this->expenses->save(
            $request->user(),
            $this->items($request),
            $this->receipts($request),
            $submit,
            $expenseClaim,
        );

        return $this->ok($submit ? 'Expense submitted.' : 'Draft saved.', $this->expenses->present($claim));
    }

    public function submit(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $claim = $this->expenses->submit($request->user(), $expenseClaim);

        return $this->ok('Expense submitted.', $this->expenses->present($claim));
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

    /**
     * @return list<array<string, mixed>>
     */
    private function items(Request $request): array
    {
        $items = $request->input('items');
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        if (! is_array($items)) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one expense.',
            ]);
        }

        return array_values($items);
    }

    /**
     * @return array<int, UploadedFile|null>
     */
    private function receipts(Request $request): array
    {
        $files = $request->file('receipts', []);

        return is_array($files) ? $files : [];
    }

    private function ok(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => $status < 400,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
