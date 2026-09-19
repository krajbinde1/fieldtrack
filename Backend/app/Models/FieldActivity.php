<?php

namespace App\Models;

use App\Enums\FieldActivityType;
use App\Support\AttendanceCalendar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldActivity extends Model
{
    protected $fillable = [
        'employee_id',
        'user_id',
        'center_id',
        'scheme_id',
        'activity_type',
        'activity_name',
        'activity_at',
        'remarks',
        'photo_path',
        'latitude',
        'longitude',
        'location',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => FieldActivityType::class,
            'activity_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function mapsUrl(): ?string
    {
        return Attendance::googleMapsUrl($this->latitude, $this->longitude);
    }

    public function photoUrl(): ?string
    {
        return \App\Support\PublicStorage::url($this->photo_path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $type = $this->activity_type instanceof FieldActivityType
            ? $this->activity_type
            : FieldActivityType::tryFromMixed((string) $this->activity_type);
        $at = $this->activity_at?->timezone(AttendanceCalendar::TIMEZONE);

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->full_name,
            'staff_role' => $this->employee?->staff_role,
            'staff_role_label' => $this->employee?->staffRoleEnum()->label(),
            'center_id' => $this->center_id,
            'center_name' => $this->center?->name,
            'scheme_id' => $this->scheme_id,
            'scheme_name' => $this->scheme?->name,
            'activity_type' => $type->value,
            'activity_type_label' => $type->label(),
            'activity_name' => $this->activity_name ?: $type->label(),
            'activity_at' => $at?->toIso8601String(),
            'activity_date' => $at?->toDateString(),
            'activity_time' => $at?->format('h:i A'),
            'remarks' => $this->remarks,
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photoUrl(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location' => $this->location,
            'maps_url' => $this->mapsUrl(),
        ];
    }
}
