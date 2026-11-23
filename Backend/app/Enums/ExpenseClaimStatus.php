<?php

namespace App\Enums;

enum ExpenseClaimStatus: string
{
    case Draft = 'draft';
    case PendingCenterManager = 'pending_center_manager';
    case PendingCenterCoordinator = 'pending_center_coordinator';
    case PendingFinal = 'pending_final';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NeedsResolution = 'needs_resolution';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingCenterManager => 'Pending Center Manager',
            self::PendingCenterCoordinator => 'Pending Center Coordinator',
            self::PendingFinal => 'Pending Final Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::NeedsResolution => 'Needs Admin Resolution',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::PendingCenterManager => 1,
            self::PendingCenterCoordinator => 2,
            self::PendingFinal => 3,
            default => 0,
        };
    }

    public function isPendingApproval(): bool
    {
        return in_array($this, [
            self::PendingCenterManager,
            self::PendingCenterCoordinator,
            self::PendingFinal,
            self::NeedsResolution,
        ], true);
    }
}
