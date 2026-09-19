<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Director = 'director';
    case ProjectHead = 'project_head';
    case CenterManager = 'center_manager';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Director => 'Director',
            self::ProjectHead => 'Project Head',
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
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function mobileValues(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function webValues(): array
    {
        return [
            self::Admin->value,
            self::Director->value,
            self::ProjectHead->value,
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
        return $this === self::Employee || $this === self::CenterManager || $this === self::ProjectHead;
    }

    public function canAccessWeb(): bool
    {
        return in_array($this, [self::Admin, self::Director, self::ProjectHead, self::CenterManager], true);
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
            self::Employee => [
                'attendance',
                'route_tracking',
                'admissions',
                'leaves',
                'admission_targets',
            ],
            self::CenterManager => [
                'center_manager_dashboard',
                'attendance_view_center',
                'route_tracking_view_center',
                'leave_manage_center',
                'admission_review_center',
                'admission_target_manage_center',
            ],
            self::ProjectHead => [
                'project_head_dashboard',
                'attendance',
                'attendance_view_project',
                'route_tracking_view_project',
                'leave_view_project',
                'admissions_view_project',
                'admission_target_view_project',
            ],
            self::Director => [
                'director_dashboard',
                'attendance_view_assigned',
                'route_tracking_view_assigned',
                'leave_view_assigned',
                'admissions_view_assigned',
                'admission_target_view_assigned',
            ],
            self::Admin => [
                'admin_dashboard',
                'attendance_view_all',
                'route_tracking_view_all',
                'leave_view_all',
                'admissions_view_all',
                'admission_target_view_all',
            ],
        };
    }
}
