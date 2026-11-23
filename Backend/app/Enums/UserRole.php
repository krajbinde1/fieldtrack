<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Director = 'director';
    case CenterCoordinator = 'center_coordinator';
    case ProjectHead = 'project_head';
    case CenterManager = 'center_manager';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Director => 'Director',
            self::CenterCoordinator => 'Center Coordinator',
            self::ProjectHead => 'Project Manager',
            self::CenterManager => 'Center Manager',
            self::Employee => 'Employee',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            if ($case === self::ProjectHead) {
                continue;
            }

            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function mobileValues(): array
    {
        return array_values(array_filter(
            array_map(
                static fn (self $role): string => $role->value,
                self::cases(),
            ),
            static fn (string $role): bool => $role !== self::ProjectHead->value,
        ));
    }

    /**
     * @return list<string>
     */
    public static function webValues(): array
    {
        return [
            self::Admin->value,
            self::Director->value,
            self::CenterCoordinator->value,
            self::CenterManager->value,
        ];
    }

    public static function tryFromMixed(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Employee;
        }

        return self::tryFrom($value) ?? self::Employee;
    }

    public function canPunch(): bool
    {
        return $this === self::Employee || $this === self::CenterManager;
    }

    public function canAccessWeb(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Director,
            self::CenterCoordinator,
            self::CenterManager,
        ], true);
    }

    public function canViewAllOrganization(): bool
    {
        return $this === self::Admin || $this === self::Director;
    }

    /**
     * @return list<string>
     */
    public function mobilePermissions(): array
    {
        return match ($this) {
            self::CenterCoordinator => [
                'coordinator_dashboard',
                'attendance_view_assigned',
                'route_tracking_view_assigned',
                'leave_view_assigned',
                'expense_approve_assigned',
                'admissions_view_assigned',
                'admission_target_view_assigned',
            ],
            self::Employee => [
                'attendance',
                'route_tracking',
                'admissions',
                'leaves',
                'expenses',
                'admission_targets',
            ],
            self::CenterManager => [
                'center_manager_dashboard',
                'attendance_view_center',
                'route_tracking_view_center',
                'leave_manage_center',
                'expense_manage_center',
                'admission_review_center',
                'admission_target_manage_center',
            ],
            self::ProjectHead => [],
            self::Director => [
                'director_dashboard',
                'attendance_view_assigned',
                'route_tracking_view_assigned',
                'leave_view_assigned',
                'expense_approve_assigned',
                'admissions_view_assigned',
                'admission_target_view_assigned',
            ],
            self::Admin => [
                'admin_dashboard',
                'attendance_view_all',
                'route_tracking_view_all',
                'leave_view_all',
                'expense_approve_all',
                'expense_payment',
                'admissions_view_all',
                'admission_target_view_all',
            ],
        };
    }
}
