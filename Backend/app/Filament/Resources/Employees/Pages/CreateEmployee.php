<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Center;
use App\Models\User;
use App\Services\CenterManagerAccountService;
use App\Services\CenterStaffCredentialService;
use App\Services\OrganizationAccessService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected bool $reusedExisting = false;

    protected ?string $managerNotice = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $submittedRole = (string) ($data['staff_role'] ?? '');
        if (CenterStaffCredentialService::rejectsLoginRole($submittedRole)
            && ! ($submittedRole === UserRole::CenterManager->value && $actor->isCenterCoordinator())) {
            throw $this->formValidationException(ValidationException::withMessages([
                'staff_role' => 'You cannot assign this login role.',
            ]));
        }

        if ($submittedRole === UserRole::CenterManager->value) {
            $access = app(OrganizationAccessService::class);
            $visible = $access->visibleCenterIds($actor) ?? [];
            $centerId = (int) ($data['center_id'] ?? 0);
            if ($centerId === 0 && count($visible) === 1) {
                $centerId = (int) $visible[0];
            }

            $data['center_id'] = $centerId;
            $data['account_kind'] = 'center_manager';
            $data['status'] = (bool) ($data['status'] ?? true);
            unset($data['login_password'], $data['login_id'], $data['scheme_name'], $data['project_name'], $data['center_manager_name'], $data['travel_km_rate'], $data['monthly_km_limit']);

            return $data;
        }

        $access = app(OrganizationAccessService::class);
        $centerIds = $access->visibleCenterIds($actor) ?? [];
        if (count($centerIds) === 1) {
            $data['center_id'] = $centerIds[0];
        }

        $center = Center::query()->find($data['center_id'] ?? null);
        abort_unless(
            $center !== null && $access->canManageCenterUsers($actor, $center),
            403,
            'You can only create users for your own Center.',
        );

        $loginId = trim((string) ($data['mobile'] ?? ''));
        if ($loginId === '') {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile Number is required.',
            ]);
        }

        $role = CenterStaffRole::tryFromMixed($data['staff_role'] ?? null);
        $data['staff_role'] = $role->value;
        $data['designation'] = $role->label();
        $data['department'] = $data['department'] ?? 'Center';
        $data['created_by_user_id'] = $actor->id;
        $data['status'] = (bool) ($data['status'] ?? true);

        unset($data['login_password'], $data['login_id'], $data['scheme_name'], $data['project_name'], $data['center_manager_name']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        if (($data['account_kind'] ?? null) === 'center_manager') {
            $actor = auth()->user();
            abort_unless($actor instanceof User && $actor->isCenterCoordinator(), 403);

            try {
                $result = app(CenterManagerAccountService::class)->createOnlyForCoordinator($actor, $data);
            } catch (ValidationException $exception) {
                throw $this->formValidationException($exception);
            }
            $manager = $result['user'];
            if ($result['created'] && ! (bool) ($data['status'] ?? true)) {
                $manager->forceFill(['is_active' => false])->save();
            }

            $this->managerNotice = $result['created'] && is_string($result['password'])
                ? CenterManagerAccountService::credentialMessage($result['login_id'], $result['password'])
                : 'Existing Center Manager assigned.';

            return $manager->fresh();
        }

        $credentials = app(CenterStaffCredentialService::class);
        $employee = $credentials->placeEmployee($data);
        $this->reusedExisting = $credentials->reusedExistingAccount;

        return $employee;
    }

    protected function getCreatedNotification(): ?Notification
    {
        if ($this->managerNotice === null) {
            return parent::getCreatedNotification();
        }

        return Notification::make()
            ->success()
            ->title($this->managerNotice)
            ->persistent();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        if ($this->reusedExisting) {
            return 'Existing user assigned to this center.';
        }

        return parent::getCreatedNotificationTitle();
    }

    private function formValidationException(ValidationException $exception): ValidationException
    {
        $messages = [];
        foreach ($exception->errors() as $key => $errors) {
            $messages[str_contains($key, '.') ? $key : 'data.'.$key] = $errors;
        }

        return ValidationException::withMessages($messages);
    }
}
