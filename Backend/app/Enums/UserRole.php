<?php

namespace App\Enums;

enum UserRole: string
{
    case Director = 'director';
    case ProjectHead = 'project_head';
    case CenterManager = 'center_manager';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
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
        return $this === self::Employee;
    }

    public function canAccessWeb(): bool
    {
        return in_array($this, [self::Director, self::ProjectHead, self::CenterManager], true);
    }

    public function canViewAllOrganization(): bool
    {
        return $this === self::Director;
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
            ],
            self::CenterManager => [
                'center_manager_dashboard',
                'attendance_view_center',
                'route_tracking_view_center',
            ],
            self::ProjectHead => [
                'project_head_dashboard',
                'attendance_view_project',
                'route_tracking_view_project',
            ],
            self::Director => [
                'director_dashboard',
                'attendance_view_all',
                'route_tracking_view_all',
            ],
        };
    }
}
