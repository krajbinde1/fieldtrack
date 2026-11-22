<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Support\UserIdentity;
use Illuminate\Support\Facades\DB;

class DuplicateUserReport
{
    /**
     * Existing duplicate accounts are reported only. Nothing is merged or deleted.
     *
     * @return list<array<string, mixed>>
     */
    public function groups(): array
    {
        return [
            ...$this->loginGroups(),
            ...$this->nameMobileGroups(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loginGroups(): array
    {
        $keys = DB::table('users')
            ->whereNotNull('login_id')
            ->selectRaw('lower(login_id) as login_key, count(*) as aggregate')
            ->groupByRaw('lower(login_id)')
            ->havingRaw('count(*) > 1')
            ->pluck('login_key');

        $groups = [];
        foreach ($keys as $key) {
            $users = User::query()->whereRaw('lower(login_id) = ?', [$key])->get();
            $groups[] = $this->group('login_id', (string) $key, $users);
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nameMobileGroups(): array
    {
        $grouped = [];
        Employee::query()->with('user')->whereNotNull('mobile')->orderBy('id')->each(function (Employee $employee) use (&$grouped): void {
            $mobile = UserIdentity::normalizeMobile((string) $employee->mobile);
            if (! UserIdentity::isMobileLogin($mobile)) {
                return;
            }

            $grouped[UserIdentity::nameKey($employee->full_name).'|'.$mobile][] = $employee;
        });

        $groups = [];
        foreach ($grouped as $key => $employees) {
            if (count($employees) < 2) {
                continue;
            }

            $users = collect($employees)->map(fn (Employee $employee) => $employee->user)->filter();
            $groups[] = $this->group('name_mobile', $key, $users, $employees);
        }

        return $groups;
    }

    /**
     * @param  iterable<User>  $users
     * @param  list<Employee>  $employees
     * @return array<string, mixed>
     */
    private function group(string $kind, string $key, iterable $users, array $employees = []): array
    {
        $accounts = [];
        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $employee = $user->employee ?? collect($employees)->first(fn (Employee $row): bool => (int) $row->id === (int) $user->employee_id);
            $accounts[] = $this->account($user, $employee instanceof Employee ? $employee : null);
        }

        foreach ($employees as $employee) {
            if ($employee->user instanceof User) {
                continue;
            }

            $accounts[] = $this->account(null, $employee);
        }

        return [
            'match' => $kind,
            'key' => $key,
            'accounts' => $accounts,
            'resolution' => $this->resolution($accounts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function account(?User $user, ?Employee $employee): array
    {
        $employee ??= $user?->employee;

        return [
            'user_id' => $user?->id,
            'employee_id' => $employee?->id,
            'name' => $employee->full_name ?? $user?->name,
            'login_id' => $user?->login_id,
            'mobile' => $employee?->mobile,
            'role' => $user?->role,
            'centers' => $employee?->assignedCenterNames() ?? $user?->assignedCenterNames() ?? [],
            'related' => [
                'attendance' => $employee ? $employee->attendances()->count() : 0,
                'admissions' => $employee ? $employee->admissions()->count() : 0,
                'routes' => $employee ? $employee->routePoints()->count() : 0,
                'leave' => $employee ? $employee->leaveRequests()->count() : 0,
                'activities' => $employee ? $employee->fieldActivities()->count() : 0,
                'targets' => $employee ? $employee->admissionTargets()->count() : 0,
            ],
            'has_related_records' => $employee?->hasRelatedRecords() ?? false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @return list<string>
     */
    private function resolution(array $accounts): array
    {
        $withRecords = array_values(array_filter($accounts, fn (array $account): bool => $account['has_related_records'] === true));
        $withoutRecords = array_values(array_filter($accounts, fn (array $account): bool => $account['has_related_records'] !== true));

        $options = [
            'Do not merge or delete these accounts automatically.',
        ];

        if ($withRecords !== []) {
            $ids = implode(', ', array_map(fn (array $account): string => 'user '.$account['user_id'], $withRecords));
            $options[] = 'Keep the account with related attendance, admissions, routes, leave, or other records ('.$ids.').';
        }

        if ($withoutRecords !== []) {
            $ids = implode(', ', array_map(fn (array $account): string => 'user '.$account['user_id'], $withoutRecords));
            $options[] = 'The account without related records ('.$ids.') can be deactivated after a manual review. Do not delete it while this report is unresolved.';
        }

        if (count($withRecords) > 1) {
            $options[] = 'More than one account has related records. Keep both until an administrator chooses which history to retain.';
        }

        return $options;
    }
}
