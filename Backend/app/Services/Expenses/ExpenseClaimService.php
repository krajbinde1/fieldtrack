<?php

namespace App\Services\Expenses;

use App\Enums\ExpenseClaimStatus;
use App\Enums\ExpensePaymentStatus;
use App\Enums\ExpenseType;
use App\Enums\UserRole;
use App\Models\Center;
use App\Models\ExpenseApproval;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Models\ExpensePayment;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\Notifications\ExpensePushNotifier;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ExpenseClaimService
{
    public function __construct(
        private readonly ExpenseApproverResolver $approvers,
        private readonly ExpensePushNotifier $notifier,
        private readonly OrganizationAccessService $access,
        private readonly AdminCenterContext $centers,
    ) {}

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return [
            'employee',
            'center',
            'scheme',
            'createdBy',
            'designatedApprover',
            'items',
            'approvals.user',
            'payment',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, UploadedFile|null>  $receipts
     */
    public function save(User $user, array $items, array $receipts, bool $submit, ?ExpenseClaim $claim = null, ?int $centerId = null): ExpenseClaim
    {
        abort_unless(
            $user->isEmployeeUser() || $user->isCenterManager(),
            403,
            'This account cannot submit expenses.',
        );

        $center = $this->submissionCenter($user, $centerId);
        $employeeId = $this->submissionEmployeeId($user, $center);

        return DB::transaction(function () use ($user, $employeeId, $center, $items, $receipts, $submit, $claim): ExpenseClaim {
            if ($claim instanceof ExpenseClaim) {
                $claim = ExpenseClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
                $this->assertOwner($user, $claim);
                if ($claim->status !== ExpenseClaimStatus::Draft) {
                    throw ValidationException::withMessages([
                        'status' => 'Only a draft can be changed.',
                    ]);
                }
            } else {
                $claim = ExpenseClaim::query()->create([
                    'employee_id' => $employeeId,
                    'center_id' => $center->id,
                    'scheme_id' => $center->scheme_id,
                    'created_by_user_id' => $user->id,
                    'claim_number' => 'EXP-TMP-'.(string) str()->uuid(),
                    'status' => ExpenseClaimStatus::Draft,
                    'payment_status' => ExpensePaymentStatus::Unpaid,
                    'current_level' => 0,
                    'total_amount' => 0,
                ]);
                $claim->update([
                    'claim_number' => sprintf('EXP-%s-%04d', AttendanceCalendar::today()->format('Y'), $claim->id),
                ]);
            }

            $this->replaceItems($claim, $items, $receipts);
            $claim->update([
                'total_amount' => $claim->items()->sum('amount'),
            ]);

            if ($submit) {
                $this->submitLocked($user, $claim->fresh());
            }

            return $claim->fresh($this->relations());
        });
    }

    public function submit(User $user, ExpenseClaim $claim): ExpenseClaim
    {
        return DB::transaction(function () use ($user, $claim): ExpenseClaim {
            $claim = ExpenseClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($user, $claim);
            $this->submitLocked($user, $claim);

            return $claim->fresh($this->relations());
        });
    }

    public function approve(User $user, ExpenseClaim $claim, ?string $remarks): ExpenseClaim
    {
        return DB::transaction(function () use ($user, $claim, $remarks): ExpenseClaim {
            $claim = ExpenseClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            $level = $this->assertCanDecide($user, $claim);
            $this->recordDecision($claim, $user, $level, 'approved', $remarks);

            if ($level >= 3) {
                $claim->update([
                    'status' => ExpenseClaimStatus::Approved,
                    'payment_status' => ExpensePaymentStatus::Unpaid,
                    'current_level' => 3,
                    'blocked_level' => null,
                    'designated_approver_id' => null,
                    'final_approved_at' => now(),
                ]);
                $this->tellMobilizer(
                    $claim->fresh(),
                    'expense_approved',
                    'Expense approved',
                    'Your expense '.$claim->claimNumber().' is approved and pending payment.',
                );

                return $claim->fresh($this->relations());
            }

            $this->routeToLevel($claim, $level + 1);

            return $claim->fresh($this->relations());
        });
    }

    public function reject(User $user, ExpenseClaim $claim, string $remarks): ExpenseClaim
    {
        $remarks = trim($remarks);
        if ($remarks === '') {
            throw ValidationException::withMessages([
                'remarks' => 'Rejection remarks are required.',
            ]);
        }

        return DB::transaction(function () use ($user, $claim, $remarks): ExpenseClaim {
            $claim = ExpenseClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            $level = $this->assertCanDecide($user, $claim);
            $this->recordDecision($claim, $user, $level, 'rejected', $remarks);
            $claim->update([
                'status' => ExpenseClaimStatus::Rejected,
                'blocked_level' => null,
                'designated_approver_id' => null,
                'rejected_at' => now(),
                'rejection_remarks' => $remarks,
            ]);
            $this->tellMobilizer(
                $claim->fresh(),
                'expense_rejected',
                'Expense rejected',
                'Your expense '.$claim->claimNumber()." was rejected.\nRemarks: {$remarks}",
            );

            return $claim->fresh($this->relations());
        });
    }

    /**
     * @param  array{amount: mixed, payment_mode: string, reference_number?: ?string, paid_on: string, remarks?: ?string}  $payload
     */
    public function markPaid(User $user, ExpenseClaim $claim, array $payload): ExpenseClaim
    {
        if (! $user->isAdminOrDirector()) {
            abort(403, 'Only an admin or director can record payment.');
        }

        return DB::transaction(function () use ($user, $claim, $payload): ExpenseClaim {
            $claim = ExpenseClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($claim->status !== ExpenseClaimStatus::Approved || $claim->payment()->exists()) {
                throw ValidationException::withMessages([
                    'payment' => 'Only an approved, unpaid claim can be marked paid.',
                ]);
            }

            $amount = round((float) $payload['amount'], 2);
            if ($amount <= 0 || abs($amount - (float) $claim->total_amount) > 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must match the approved claim total.',
                ]);
            }

            ExpensePayment::query()->create([
                'expense_claim_id' => $claim->id,
                'amount' => $amount,
                'payment_mode' => $payload['payment_mode'],
                'reference_number' => $payload['reference_number'] ?? null,
                'paid_on' => $payload['paid_on'],
                'remarks' => $payload['remarks'] ?? null,
                'paid_by_user_id' => $user->id,
                'paid_by_name' => $user->name,
            ]);
            $claim->update(['payment_status' => ExpensePaymentStatus::Paid]);
            $this->tellMobilizer(
                $claim->fresh(),
                'expense_paid',
                'Expense paid',
                'Your expense '.$claim->claimNumber().' has been paid.',
            );

            return $claim->fresh($this->relations());
        });
    }

    public function assertCanView(User $user, ExpenseClaim $claim): void
    {
        if ((int) $claim->created_by_user_id === (int) $user->id) {
            return;
        }

        if (! $this->centerIsVisible($user, (int) $claim->center_id)) {
            abort(403, 'You cannot view this expense.');
        }

        if ($user->isAdminOrDirector() || $user->isCenterCoordinator() || $user->isCenterManager()) {
            return;
        }

        abort(403, 'You cannot view this expense.');
    }

    /**
     * @return array{total_submitted: string, pending_approval: string, approved: string, paid: string}
     */
    public function summaryFor(User $user): array
    {
        $rows = ExpenseClaim::query()
            ->where('created_by_user_id', $user->id)
            ->where('status', '!=', ExpenseClaimStatus::Draft->value)
            ->get(['status', 'payment_status', 'total_amount']);

        $sum = function (callable $filter) use ($rows): string {
            return number_format((float) $rows->filter($filter)->sum(fn (ExpenseClaim $claim) => (float) $claim->total_amount), 2, '.', '');
        };

        return [
            'total_submitted' => $sum(fn (): bool => true),
            'pending_approval' => $sum(fn (ExpenseClaim $claim): bool => $claim->status->isPendingApproval()),
            'approved' => $sum(fn (ExpenseClaim $claim): bool => $claim->status === ExpenseClaimStatus::Approved && $claim->payment_status === ExpensePaymentStatus::Unpaid),
            'paid' => $sum(fn (ExpenseClaim $claim): bool => $claim->payment_status === ExpensePaymentStatus::Paid),
        ];
    }

    public function queueQuery(User $user): Builder
    {
        $query = $this->visibleQuery($user);

        if ($user->isAdmin()) {
            return $query->whereIn('status', [
                ExpenseClaimStatus::PendingFinal->value,
                ExpenseClaimStatus::NeedsResolution->value,
            ]);
        }

        if ($user->isDirector()) {
            return $query->where('status', ExpenseClaimStatus::PendingFinal->value);
        }

        if ($user->isCenterCoordinator()) {
            return $query
                ->where('status', ExpenseClaimStatus::PendingCenterCoordinator->value)
                ->where('created_by_user_id', '!=', $user->id);
        }

        if ($user->isCenterManager()) {
            return $query
                ->where('status', ExpenseClaimStatus::PendingCenterManager->value)
                ->where('created_by_user_id', '!=', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function visibleQuery(User $user): Builder
    {
        $query = ExpenseClaim::query();
        $centers = $this->access->visibleCenterIds($user);
        if (is_array($centers)) {
            $query->whereIn('center_id', $centers === [] ? [-1] : $centers);
        }

        $selected = $this->centers->idFor($user);
        if ($selected !== null) {
            $query->where('center_id', $selected);
        }

        return $query;
    }

    public function pendingCountFor(?User $user): int
    {
        if (! $user instanceof User) {
            return 0;
        }

        return $this->queueQuery($user)->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ExpenseClaim $claim): array
    {
        return [
            'id' => $claim->id,
            'claim_number' => $claim->claim_number,
            'status' => $claim->status->value,
            'status_label' => $claim->displayStatus(),
            'payment_status' => $claim->payment_status->value,
            'payment_status_label' => $claim->payment_status->label(),
            'current_level' => $claim->current_level,
            'total_amount' => number_format((float) $claim->total_amount, 2, '.', ''),
            'employee_name' => $claim->employee?->full_name ?: $claim->createdBy?->name,
            'center_name' => $claim->center?->name,
            'center_id' => $claim->center_id,
            'submitted_at' => $claim->submitted_at?->toDateTimeString(),
            'final_approved_at' => $claim->final_approved_at?->toDateTimeString(),
            'rejected_at' => $claim->rejected_at?->toDateTimeString(),
            'rejection_remarks' => $claim->rejection_remarks,
            'items' => $claim->items->map(fn (ExpenseItem $item): array => [
                'id' => $item->id,
                'expense_date' => $item->expense_date?->toDateString(),
                'type' => $item->type->value,
                'type_label' => $item->type->label(),
                'amount' => number_format((float) $item->amount, 2, '.', ''),
                'description' => $item->description,
                'has_receipt' => $item->hasReceipt(),
                'travel_from' => $item->travel_from,
                'travel_to' => $item->travel_to,
                'travel_km' => $item->travel_km,
            ])->values()->all(),
            'approvals' => $claim->approvals->map(fn (ExpenseApproval $approval): array => [
                'level' => $approval->level,
                'action' => $approval->action,
                'approver_name' => $approval->approver_name,
                'role' => $approval->role,
                'remarks' => $approval->remarks,
                'created_at' => $approval->created_at?->toDateTimeString(),
            ])->values()->all(),
            'payment' => $claim->payment === null ? null : [
                'amount' => number_format((float) $claim->payment->amount, 2, '.', ''),
                'payment_mode' => $claim->payment->payment_mode,
                'reference_number' => $claim->payment->reference_number,
                'paid_on' => $claim->payment->paid_on?->toDateString(),
                'remarks' => $claim->payment->remarks,
                'paid_by_name' => $claim->payment->paid_by_name,
            ],
        ];
    }

    private function submitLocked(User $user, ExpenseClaim $claim): void
    {
        if ($claim->status !== ExpenseClaimStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'This claim has already been submitted.',
            ]);
        }

        $claim->load('items');
        if ($claim->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one expense before submitting.',
            ]);
        }

        $claim->update([
            'submitted_at' => now(),
            'total_amount' => $claim->items->sum(fn (ExpenseItem $item) => (float) $item->amount),
        ]);
        $this->routeToLevel($claim, $this->openingLevel($claim), false);
        $this->tellMobilizer(
            $claim->fresh(),
            'expense_submitted',
            'Expense submitted',
            'Your expense '.$claim->claimNumber().' was submitted for approval.',
        );
    }

    private function submissionCenter(User $user, ?int $requestedCenterId): Center
    {
        if ($user->isEmployeeUser()) {
            $employee = $user->employee;
            $center = $employee?->center;
            if ($employee === null || $center === null) {
                throw ValidationException::withMessages([
                    'center' => 'This account is not assigned to a center.',
                ]);
            }

            return $center;
        }

        $managed = $user->managedCenters()->orderBy('centers.id')->get();
        $selected = $requestedCenterId ?? app(AdminCenterContext::class)->idFor($user);
        $center = $selected !== null
            ? $managed->firstWhere('id', $selected)
            : ($managed->count() === 1 ? $managed->first() : null);

        if (! $center instanceof Center) {
            throw ValidationException::withMessages([
                'center' => 'Choose the center this expense belongs to.',
            ]);
        }

        return $center;
    }

    private function submissionEmployeeId(User $user, Center $center): ?int
    {
        if ($user->isEmployeeUser()) {
            return $user->employee_id !== null ? (int) $user->employee_id : null;
        }

        $employee = $user->employee;

        return $employee !== null && (int) $employee->center_id === (int) $center->id
            ? (int) $employee->id
            : null;
    }

    private function openingLevel(ExpenseClaim $claim): int
    {
        $claim->loadMissing('createdBy');

        return $claim->createdBy?->isCenterManager() === true ? 2 : 1;
    }

    private function routeToLevel(ExpenseClaim $claim, int $level, bool $notifySubmitter = true): void
    {
        $claim->loadMissing('center');
        $center = $claim->center;
        $claimantId = (int) $claim->created_by_user_id;

        if ($level >= 3) {
            $pool = $center === null ? collect() : $this->approvers->finalApprovers($claimantId);
            if ($pool->isEmpty()) {
                $this->block($claim, 3);

                return;
            }

            $claim->update([
                'status' => ExpenseClaimStatus::PendingFinal,
                'current_level' => 3,
                'blocked_level' => null,
                'designated_approver_id' => null,
            ]);
            $fresh = $claim->fresh();
            $this->notifier->notifyUsers(
                $pool,
                $fresh,
                'expense_pending_final',
                'Expense awaiting final approval',
                $fresh->claimNumber().' is ready for final approval.',
                '/director/expenses/'.$fresh->id,
            );
            if ($notifySubmitter) {
                $this->tellMobilizer($fresh, 'expense_level_approved', 'Expense moved forward', $fresh->claimNumber().' was approved and is awaiting final approval.');
            }

            return;
        }

        $approvers = $center === null ? collect() : ($level === 1
            ? $this->approvers->managers($center, $claimantId)
            : $this->approvers->coordinators($center, $claimantId));

        if ($approvers->isEmpty()) {
            $this->block($claim, $level);

            return;
        }

        $claim->update([
            'status' => $level === 1
                ? ExpenseClaimStatus::PendingCenterManager
                : ExpenseClaimStatus::PendingCenterCoordinator,
            'current_level' => $level,
            'blocked_level' => null,
            'designated_approver_id' => $approvers->first()->id,
        ]);
        $fresh = $claim->fresh();
        $route = $level === 1
            ? '/manager/expenses/'.$fresh->id
            : '/director/expenses/'.$fresh->id;
        $this->notifier->notifyUsers(
            $approvers,
            $fresh,
            'expense_pending_level_'.$level,
            'Expense awaiting your approval',
            $fresh->claimNumber().' needs your approval.',
            $route,
        );

        if ($notifySubmitter && $level > 1) {
            $this->tellMobilizer($fresh, 'expense_level_approved', 'Expense approved', $fresh->claimNumber().' moved to the next approver.');
        }
    }

    private function block(ExpenseClaim $claim, int $level): void
    {
        $claim->update([
            'status' => ExpenseClaimStatus::NeedsResolution,
            'current_level' => $level,
            'blocked_level' => $level,
            'designated_approver_id' => null,
        ]);
        $fresh = $claim->fresh();
        $admins = User::query()
            ->where('is_active', true)
            ->where('role', UserRole::Admin->value)
            ->where('id', '!=', $fresh->created_by_user_id)
            ->get();
        $this->notifier->notifyUsers(
            $admins,
            $fresh,
            'expense_needs_resolution_'.$level,
            'Expense needs admin resolution',
            $fresh->claimNumber().' has no approver at level '.$level.'.',
            '/director/expenses/'.$fresh->id,
        );
        $this->tellMobilizer(
            $fresh,
            'expense_needs_resolution',
            'Expense waiting for admin',
            $fresh->claimNumber().' is waiting because an approver is not assigned.',
        );
    }

    public function canDecide(User $user, ExpenseClaim $claim): bool
    {
        try {
            $this->assertCanDecide($user, $claim);

            return true;
        } catch (ValidationException|HttpException) {
            return false;
        }
    }

    private function assertCanDecide(User $user, ExpenseClaim $claim): int
    {
        $this->assertNotSelf($user, $claim);
        if (! $this->centerIsVisible($user, (int) $claim->center_id)) {
            abort(403, 'This expense is outside your centers.');
        }

        if ($claim->status === ExpenseClaimStatus::NeedsResolution) {
            if (! $user->isAdmin() || $claim->blocked_level === null) {
                abort(403, 'This expense is waiting for an admin to resolve the missing approver.');
            }

            return (int) $claim->blocked_level;
        }

        $expected = match ($claim->status) {
            ExpenseClaimStatus::PendingCenterManager => [$user->isCenterManager(), 1],
            ExpenseClaimStatus::PendingCenterCoordinator => [$user->isCenterCoordinator(), 2],
            ExpenseClaimStatus::PendingFinal => [$user->isAdminOrDirector(), 3],
            default => [false, 0],
        };

        if ($expected[0] !== true) {
            abort(403, 'This expense is not waiting for your approval.');
        }

        $level = (int) $expected[1];
        if ($level < 3 && ! $this->userCanActAtLevel($user, $claim, $level)) {
            abort(403, 'This expense is outside your center assignment.');
        }

        if ($claim->approvals()->where('level', $level)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This approval level has already been decided.',
            ]);
        }

        return $level;
    }

    private function userCanActAtLevel(User $user, ExpenseClaim $claim, int $level): bool
    {
        $claim->loadMissing('center');
        $center = $claim->center;
        if ($center === null) {
            return false;
        }

        $claimantId = (int) $claim->created_by_user_id;
        $pool = $level === 1
            ? $this->approvers->managers($center, $claimantId)
            : $this->approvers->coordinators($center, $claimantId);

        return $pool->contains(fn (User $approver): bool => (int) $approver->id === (int) $user->id);
    }

    private function recordDecision(ExpenseClaim $claim, User $user, int $level, string $action, ?string $remarks): void
    {
        ExpenseApproval::query()->create([
            'expense_claim_id' => $claim->id,
            'level' => $level,
            'action' => $action,
            'user_id' => $user->id,
            'approver_name' => $user->name,
            'role' => $user->roleEnum()->label(),
            'remarks' => $remarks !== null ? trim($remarks) : null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, UploadedFile|null>  $receipts
     */
    private function replaceItems(ExpenseClaim $claim, array $items, array $receipts): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one expense.',
            ]);
        }

        $previous = $claim->items()->orderBy('sort_order')->orderBy('id')->get()->values();

        foreach (array_values($items) as $index => $item) {
            $type = ExpenseType::tryFrom((string) ($item['type'] ?? ''));
            $amount = round((float) ($item['amount'] ?? 0), 2);
            $date = (string) ($item['expense_date'] ?? '');
            $description = trim((string) ($item['description'] ?? ''));

            if ($type === null || $date === '' || $description === '' || $amount <= 0 || $amount > 9999999.99) {
                throw ValidationException::withMessages([
                    'items' => 'Each expense needs a date, type, description, and a valid amount.',
                ]);
            }

            if ($date > AttendanceCalendar::today()->toDateString()) {
                throw ValidationException::withMessages([
                    'items' => 'Expense date cannot be in the future.',
                ]);
            }

            $stored = [
                'expense_date' => $date,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'travel_from' => $this->nullableString($item['travel_from'] ?? null),
                'travel_to' => $this->nullableString($item['travel_to'] ?? null),
                'travel_km' => filled($item['travel_km'] ?? null) ? round((float) $item['travel_km'], 2) : null,
                'sort_order' => $index,
            ];

            $file = $receipts[$index] ?? null;
            $old = $previous->get($index);
            if ($file instanceof UploadedFile) {
                $this->assertReceipt($file);
                $path = $file->store('expenses/'.$claim->id, 'local');
                $stored['receipt_disk'] = 'local';
                $stored['receipt_path'] = $path;
                $stored['receipt_original_name'] = $file->getClientOriginalName();
                $stored['receipt_mime'] = $file->getMimeType();
            } elseif ($old instanceof ExpenseItem && $old->hasReceipt()) {
                $stored['receipt_disk'] = $old->receipt_disk;
                $stored['receipt_path'] = $old->receipt_path;
                $stored['receipt_original_name'] = $old->receipt_original_name;
                $stored['receipt_mime'] = $old->receipt_mime;
                $old->receipt_path = null;
            }

            $claim->items()->create($stored);
        }

        $previous->each(function (ExpenseItem $item): void {
            $item->deleteStoredReceipt();
            $item->delete();
        });
    }

    private function assertReceipt(UploadedFile $file): void
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (! in_array($file->getMimeType(), $allowed, true) || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'receipts' => 'Receipts must be a JPG, PNG, WEBP, or PDF no larger than 5 MB.',
            ]);
        }
    }

    private function tellMobilizer(ExpenseClaim $claim, string $type, string $title, string $body): void
    {
        $mobilizer = $claim->createdBy;
        if (! $mobilizer instanceof User) {
            return;
        }

        $this->notifier->notifyUser($mobilizer, $claim, $type, $title, $body, '/expenses/'.$claim->id);
    }

    private function assertOwner(User $user, ExpenseClaim $claim): void
    {
        abort_unless((int) $claim->created_by_user_id === (int) $user->id, 403, 'You can only change your own expenses.');
    }

    private function assertNotSelf(User $user, ExpenseClaim $claim): void
    {
        if ((int) $claim->created_by_user_id === (int) $user->id) {
            abort(403, 'You cannot approve your own expense.');
        }
    }

    private function centerIsVisible(User $user, int $centerId): bool
    {
        $visible = $this->access->visibleCenterIds($user);

        return $visible === null || in_array($centerId, $visible, true);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
