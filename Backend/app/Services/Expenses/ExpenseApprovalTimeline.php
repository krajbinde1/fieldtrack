<?php

namespace App\Services\Expenses;

use App\Enums\ExpenseClaimStatus;
use App\Enums\ExpensePaymentStatus;
use App\Models\ExpenseApproval;
use App\Models\ExpenseClaim;
use App\Models\User;
use DateTimeInterface;

/**
 * Read-only approval timeline built from the claim, its approval rows, and the current approver pool.
 * It does not route, approve, reject, or record payment.
 */
final class ExpenseApprovalTimeline
{
    public function __construct(
        private readonly ExpenseApproverResolver $approvers,
    ) {}

    /**
     * @return array{visible: bool, pending_text: ?string, stages: list<array<string, mixed>>}
     */
    public function forClaim(ExpenseClaim $claim): array
    {
        $claim->loadMissing(['employee', 'createdBy', 'approvals', 'payment.paidBy', 'center']);

        if ($claim->status === ExpenseClaimStatus::Draft) {
            return [
                'visible' => false,
                'pending_text' => null,
                'stages' => [],
            ];
        }

        $decisions = [];
        foreach ($claim->approvals as $approval) {
            $decisions[(int) $approval->level] = $approval;
        }

        $pendingLevel = $this->pendingLevel($claim);
        $stages = [$this->createdStage($claim)];

        if ($claim->createdBy?->isCenterManager() !== true) {
            $stages[] = $this->approvalStage(
                $claim,
                1,
                'center_manager',
                'Center Manager Approval',
                'Center Manager',
                $decisions[1] ?? null,
                $pendingLevel,
            );
        }

        $stages[] = $this->approvalStage(
            $claim,
            2,
            'center_coordinator',
            'Center Coordinator Approval',
            'Center Coordinator',
            $decisions[2] ?? null,
            $pendingLevel,
        );
        $stages[] = $this->approvalStage(
            $claim,
            3,
            'final',
            'Admin/Director Final Approval',
            'Admin / Director',
            $decisions[3] ?? null,
            $pendingLevel,
        );
        $stages[] = $this->paymentStage($claim);

        return [
            'visible' => true,
            'pending_text' => $this->pendingText($stages),
            'stages' => $stages,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $stages
     */
    private function pendingText(array $stages): ?string
    {
        foreach ($stages as $stage) {
            if ($stage['status'] !== 'pending' || $stage['key'] === 'payment') {
                continue;
            }

            $names = $stage['pending_names'];
            if ($names === []) {
                return $stage['title'].' — no approver is assigned';
            }

            return $stage['title'].' — waiting on '.implode(', ', $names);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function createdStage(ExpenseClaim $claim): array
    {
        $employee = $claim->employee;
        $role = filled($employee?->staff_role)
            ? $employee->staffRoleEnum()->label()
            : $claim->createdBy?->roleEnum()->label();
        $name = $employee?->full_name ?: $claim->createdBy?->name;
        $stamp = $this->stamp($claim->submitted_at);

        return $this->node(
            'created',
            'Request Created',
            filled($name) ? $name : null,
            filled($role) ? $role : null,
            $stamp['date'],
            $stamp['time'],
            $stamp['acted_at'],
            'completed',
            'Submitted',
            'green',
            null,
            [],
            null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalStage(
        ExpenseClaim $claim,
        int $level,
        string $key,
        string $title,
        string $stageRole,
        ?ExpenseApproval $decision,
        ?int $pendingLevel,
    ): array {
        if ($decision instanceof ExpenseApproval) {
            $rejected = $decision->action === 'rejected';
            $stamp = $this->stamp($decision->created_at);

            return $this->node(
                $key,
                $title,
                filled($decision->approver_name) ? $decision->approver_name : null,
                filled($decision->role) ? $decision->role : null,
                $stamp['date'],
                $stamp['time'],
                $stamp['acted_at'],
                $rejected ? 'rejected' : 'completed',
                $rejected ? 'Rejected' : 'Approved',
                $rejected ? 'red' : 'green',
                filled($decision->remarks) ? $decision->remarks : null,
                [],
                null,
            );
        }

        if ($pendingLevel === $level) {
            $names = $this->pendingNames($claim, $level);

            return $this->node(
                $key,
                $title,
                $names === [] ? null : implode(', ', $names),
                $stageRole,
                null,
                null,
                null,
                'pending',
                'Pending',
                'orange',
                null,
                $names,
                $names === [] ? 'No approver is assigned' : null,
            );
        }

        return $this->node(
            $key,
            $title,
            null,
            $stageRole,
            null,
            null,
            null,
            'not_started',
            'Not Started',
            'grey',
            null,
            [],
            null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentStage(ExpenseClaim $claim): array
    {
        $payment = $claim->payment;
        if ($claim->payment_status === ExpensePaymentStatus::Paid && $payment !== null) {
            $stamp = $this->stamp($payment->created_at);
            $paidOn = $payment->paid_on;
            $payer = $payment->paidBy;

            return $this->node(
                'payment',
                'Payment Done',
                filled($payment->paid_by_name) ? $payment->paid_by_name : null,
                $payer !== null ? $payer->roleEnum()->label() : null,
                $paidOn?->format('d M Y') ?? $stamp['date'],
                $stamp['time'],
                $stamp['acted_at'],
                'completed',
                'Paid',
                'green',
                filled($payment->remarks) ? $payment->remarks : null,
                [],
                null,
            );
        }

        if ($claim->status === ExpenseClaimStatus::Approved && $claim->payment_status === ExpensePaymentStatus::Unpaid) {
            return $this->node(
                'payment',
                'Payment Done',
                null,
                null,
                null,
                null,
                null,
                'pending',
                'Pending',
                'orange',
                null,
                [],
                null,
            );
        }

        return $this->node(
            'payment',
            'Payment Done',
            null,
            null,
            null,
            null,
            null,
            'not_started',
            'Not Started',
            'grey',
            null,
            [],
            null,
        );
    }

    private function pendingLevel(ExpenseClaim $claim): ?int
    {
        return match ($claim->status) {
            ExpenseClaimStatus::PendingCenterManager => 1,
            ExpenseClaimStatus::PendingCenterCoordinator => 2,
            ExpenseClaimStatus::PendingFinal => 3,
            ExpenseClaimStatus::NeedsResolution => $claim->blocked_level !== null
                ? (int) $claim->blocked_level
                : ((int) $claim->current_level > 0 ? (int) $claim->current_level : null),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function pendingNames(ExpenseClaim $claim, int $level): array
    {
        $claim->loadMissing('center');
        $center = $claim->center;
        $claimantId = (int) $claim->created_by_user_id;
        $pool = match ($level) {
            1 => $center === null ? collect() : $this->approvers->managers($center, $claimantId),
            2 => $center === null ? collect() : $this->approvers->coordinators($center, $claimantId),
            3 => $this->approvers->finalApprovers($claimantId),
            default => collect(),
        };

        return $pool
            ->map(fn (User $user): string => $user->name)
            ->values()
            ->all();
    }

    /**
     * @return array{date: ?string, time: ?string, acted_at: ?string}
     */
    private function stamp(?DateTimeInterface $at): array
    {
        if ($at === null) {
            return ['date' => null, 'time' => null, 'acted_at' => null];
        }

        return [
            'date' => $at->format('d M Y'),
            'time' => $at->format('h:i A'),
            'acted_at' => $at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  list<string>  $pendingNames
     * @return array<string, mixed>
     */
    private function node(
        string $key,
        string $title,
        ?string $personName,
        ?string $role,
        ?string $date,
        ?string $time,
        ?string $actedAt,
        string $status,
        string $statusLabel,
        string $color,
        ?string $remarks,
        array $pendingNames,
        ?string $detail,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'person_name' => $personName,
            'role' => $role,
            'date' => $date,
            'time' => $time,
            'acted_at' => $actedAt,
            'status' => $status,
            'status_label' => $statusLabel,
            'color' => $color,
            'remarks' => $remarks,
            'pending_names' => $pendingNames,
            'detail' => $detail,
        ];
    }
}
