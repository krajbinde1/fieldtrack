<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Models\User;
use App\Services\OrganizationAccessService;
use App\Support\LoginId;
use App\Support\UserIdentity;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrgUser extends CreateRecord
{
    protected static string $resource = OrgUserResource::class;

    protected bool $reusedExisting = false;

    /** @var list<int> */
    protected array $preservedCenterIds = [];

    protected ?string $preservedRelation = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        $allowed = app(OrganizationAccessService::class)->creatableOrgUserRoles($actor);
        abort_unless(in_array($data['role'] ?? '', $allowed, true), 403);

        $data['login_id'] = LoginId::resolveFromEmail(
            $data['login_id'] ?? null,
            $data['email'] ?? $this->data['email'] ?? null,
        );
        LoginId::assertUnique($data['login_id']);
        $role = (string) ($data['role'] ?? '');
        UserIdentity::assertAvailable(
            (string) ($data['name'] ?? ''),
            (string) $data['login_id'],
            allowManagerReuse: $role === UserRole::CenterManager->value,
            allowCoordinatorReuse: $role === UserRole::CenterCoordinator->value,
            attribute: 'login_id',
        );
        $data['must_change_password'] = $data['must_change_password'] ?? true;
        unset($data['is_center_login']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $existing = UserIdentity::userByLogin((string) ($data['login_id'] ?? ''));
        $role = (string) ($data['role'] ?? '');
        $sameName = $existing instanceof User
            && UserIdentity::nameKey($existing->name) === UserIdentity::nameKey((string) ($data['name'] ?? ''));
        $reusable = $sameName && (
            ($role === UserRole::CenterManager->value && $existing->isCenterManager() && $existing->is_center_login !== true)
            || ($role === UserRole::CenterCoordinator->value && $existing->isCenterCoordinator() && $existing->is_center_login !== true)
        );

        if (! $reusable || ! $existing instanceof User) {
            return parent::handleRecordCreation($data);
        }

        $this->reusedExisting = true;
        $this->preservedRelation = $existing->isCenterCoordinator() ? 'coordinatedCenters' : 'managedCenters';
        $this->preservedCenterIds = $existing->{$this->preservedRelation}()->pluck('centers.id')->map(fn ($id): int => (int) $id)->all();

        return $existing;
    }

    protected function afterCreate(): void
    {
        if ($this->preservedRelation === null || $this->preservedCenterIds === []) {
            return;
        }

        $this->record->{$this->preservedRelation}()->syncWithoutDetaching($this->preservedCenterIds);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        if ($this->reusedExisting) {
            return 'Existing user assigned to this center.';
        }

        return parent::getCreatedNotificationTitle();
    }
}
