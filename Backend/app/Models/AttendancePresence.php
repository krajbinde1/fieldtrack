<?php

namespace App\Models;

use App\Casts\NullableBoolean;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePresence extends Model
{
    protected $table = 'attendance_presence';

    protected $fillable = [
        'attendance_id',
        'employee_id',
        'last_heartbeat_at',
        'last_gps_at',
        'last_latitude',
        'last_longitude',
        'last_location_label',
        'gps_services_enabled',
        'gps_fix_valid',
        'gps_state_at',
        'connectivity_state',
        'connectivity_event_at',
        'alert_kind',
        'alert_episode',
        'alert_sequence',
        'last_alerted_at',
        'condition_since',
        'monitoring_stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'last_gps_at' => 'datetime',
            'last_latitude' => 'decimal:7',
            'last_longitude' => 'decimal:7',
            'gps_services_enabled' => NullableBoolean::class,
            'gps_fix_valid' => NullableBoolean::class,
            'gps_state_at' => 'datetime',
            'connectivity_event_at' => 'datetime',
            'alert_episode' => 'integer',
            'alert_sequence' => 'integer',
            'last_alerted_at' => 'datetime',
            'condition_since' => 'datetime',
            'monitoring_stopped_at' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
