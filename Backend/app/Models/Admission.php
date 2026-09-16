<?php

namespace App\Models;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionStatus;
use App\Support\AdmissionLookups;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admission extends Model
{
    protected $fillable = [
        'scheme_id',
        'project_id',
        'center_id',
        'employee_id',
        'created_by_user_id',
        'first_name',
        'middle_name',
        'last_name',
        'full_name',
        'gender',
        'religion',
        'caste',
        'state',
        'district_id',
        'taluka_id',
        'village',
        'status',
        'current_step',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdmissionStatus::class,
            'current_step' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Admission $admission): void {
            $admission->full_name = $admission->composeFullName();
            $admission->state = $admission->state ?: AdmissionLookups::STATE_MAHARASHTRA;
        });
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(MaharashtraDistrict::class, 'district_id');
    }

    public function taluka(): BelongsTo
    {
        return $this->belongsTo(MaharashtraTaluka::class, 'taluka_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class);
    }

    public function isDraft(): bool
    {
        return $this->status === AdmissionStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === AdmissionStatus::Submitted;
    }

    public function composeFullName(): string
    {
        return trim(collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])->filter(fn ($part) => filled($part))->implode(' '));
    }

    public function documentFor(AdmissionDocumentType|string $type): ?AdmissionDocument
    {
        $value = $type instanceof AdmissionDocumentType ? $type->value : $type;

        return $this->documents->firstWhere('document_type', $value);
    }

    /**
     * @return list<string>
     */
    public function missingSubmitFields(): array
    {
        $missing = [];

        if (! $this->scheme_id) {
            $missing[] = 'Scheme';
        }
        if (! filled($this->first_name)) {
            $missing[] = 'First Name';
        }
        if (! filled($this->last_name)) {
            $missing[] = 'Last Name';
        }
        if (! filled($this->gender)) {
            $missing[] = 'Gender';
        }
        if (! filled($this->religion)) {
            $missing[] = 'Religion';
        }
        if (! filled($this->caste)) {
            $missing[] = 'Caste';
        }
        if (! filled($this->state)) {
            $missing[] = 'State';
        }
        if (! $this->district_id) {
            $missing[] = 'District';
        }
        if (! $this->taluka_id) {
            $missing[] = 'Taluka';
        }
        if (! filled($this->village)) {
            $missing[] = 'Village';
        }

        foreach (AdmissionDocumentType::cases() as $type) {
            if ($type->requiredOnSubmit() && $this->documentFor($type) === null) {
                $missing[] = $type->label();
            }
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $this->loadMissing([
            'scheme:id,name,code,is_active',
            'project:id,name,code',
            'center:id,name,code,project_id',
            'employee:id,full_name,employee_code,center_id',
            'district:id,name,code',
            'taluka:id,name,code,district_id',
            'documents',
        ]);

        return [
            'id' => $this->id,
            'status' => $this->status?->value ?? AdmissionStatus::Draft->value,
            'status_label' => $this->status?->label() ?? 'Draft',
            'current_step' => (int) ($this->current_step ?: 1),
            'scheme_id' => $this->scheme_id,
            'scheme' => $this->scheme ? [
                'id' => $this->scheme->id,
                'name' => $this->scheme->name,
                'code' => $this->scheme->code,
                'is_active' => $this->scheme->is_active,
            ] : null,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
            ] : null,
            'center' => $this->center ? [
                'id' => $this->center->id,
                'name' => $this->center->name,
                'code' => $this->center->code,
            ] : null,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ] : null,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name ?: $this->composeFullName(),
            'gender' => $this->gender,
            'religion' => $this->religion,
            'caste' => $this->caste,
            'state' => $this->state ?: AdmissionLookups::STATE_MAHARASHTRA,
            'district_id' => $this->district_id,
            'district' => $this->district ? [
                'id' => $this->district->id,
                'name' => $this->district->name,
            ] : null,
            'taluka_id' => $this->taluka_id,
            'taluka' => $this->taluka ? [
                'id' => $this->taluka->id,
                'name' => $this->taluka->name,
                'district_id' => $this->taluka->district_id,
            ] : null,
            'village' => $this->village,
            'documents' => $this->documents->map(fn (AdmissionDocument $document) => $document->toApiArray())->values()->all(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'editable' => $this->isDraft(),
        ];
    }

    public function addressLabel(): string
    {
        return collect([
            $this->village,
            $this->taluka?->name,
            $this->district?->name,
            $this->state ?: AdmissionLookups::STATE_MAHARASHTRA,
        ])->filter()->implode(', ');
    }
}
