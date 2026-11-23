<?php

use App\Filament\Resources\ExpenseClaims\Pages\CreateExpenseClaim;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeRoutePoint;
use App\Models\ExpenseSelfTravelDay;
use App\Services\AdminCenterContext;
use App\Services\RouteDistanceCalculator;
use App\Support\AttendanceCalendar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

function selfTravelRoute(Employee $employee, string $date, bool $punchedOut = true, bool $withPoints = true): Attendance
{
    $attendance = Attendance::query()->create([
        'employee_id' => $employee->id,
        'attendance_date' => $date,
        'punch_in_time' => '09:00:00',
        'punch_out_time' => $punchedOut ? '18:00:00' : null,
        'attendance_status' => $punchedOut ? 'Present' : 'Punched In',
        'approval_status' => 'Pending',
    ]);

    if ($withPoints) {
        $start = Carbon::parse($date.' 09:00:00', AttendanceCalendar::TIMEZONE);
        foreach ([
            [18.5204, 73.8567, 0],
            [18.5294, 73.8567, 10],
            [18.5294, 73.8667, 20],
        ] as [$latitude, $longitude, $minutes]) {
            EmployeeRoutePoint::query()->create([
                'attendance_id' => $attendance->id,
                'employee_id' => $employee->id,
                'local_uuid' => (string) Str::uuid(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => 12,
                'speed' => 4,
                'heading' => 20,
                'recorded_at' => $start->copy()->addMinutes($minutes),
                'source' => 'test',
            ]);
        }
    }

    return $attendance->fresh('routePoints');
}

function selfTravelPayload(string $date, array $overrides = []): array
{
    return [
        'action' => 'submit',
        'items' => json_encode([[
            'expense_date' => $date,
            'type' => 'travelling',
            'travel_mode' => 'self',
            'amount' => 9999,
            'description' => 'Self travel',
            'travel_from' => 'Manual Start',
            'travel_to' => 'Manual End',
            'travel_from_source' => 'manual',
            'travel_to_source' => 'manual',
            'gps_from_address' => 'Suggested Start',
            'gps_to_address' => 'Suggested End',
            'travel_km' => 1,
            'travel_from_latitude' => 1,
            'travel_from_longitude' => 1,
            ...$overrides,
        ]]),
    ];
}

it('calculates self travelling from the recorded route and ignores the client amount', function () {
    $org = seedOrg();
    $date = '2026-11-20';
    $org['empA']->update(['travel_km_rate' => 8, 'monthly_km_limit' => 500]);
    $attendance = selfTravelRoute($org['empA'], $date);
    $expected = app(RouteDistanceCalculator::class)->calculate($attendance->routePoints);

    $response = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', selfTravelPayload($date))
        ->assertCreated();

    $item = $response->json('data.items.0');
    expect((float) $expected['total_distance_km'])->toBeGreaterThan(1.5)
        ->and((float) $item['recorded_km'])->toEqual((float) $expected['total_distance_km'])
        ->and((float) $item['eligible_km'])->toEqual((float) $expected['total_distance_km'])
        ->and((float) $item['amount'])->toEqual(round((float) $expected['total_distance_km'] * 8, 2))
        ->and((float) $item['km_rate'])->toEqual(8.0)
        ->and($item['travel_from'])->toBe('Manual Start')
        ->and($item['gps_from_address'])->toBe('Suggested Start')
        ->and((float) $item['travel_from_latitude'])->toEqual(18.5204)
        ->and((float) $item['travel_to_latitude'])->toEqual(18.5294)
        ->and((float) $item['travel_to_longitude'])->toEqual(73.8667)
        ->and($item['travel_from_source'])->toBe('manual');

    $this->actingAs($org['userA'], 'sanctum')
        ->getJson('/api/expenses/self-travel?date='.$date)
        ->assertOk()
        ->assertJsonPath('data.available', false);
});

it('caps self travelling at the remaining monthly allowance', function () {
    $org = seedOrg();
    $org['empA']->update(['travel_km_rate' => 10, 'monthly_km_limit' => 1]);
    selfTravelRoute($org['empA'], '2026-11-20');

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', selfTravelPayload('2026-11-20'))
        ->assertCreated()
        ->assertJsonPath('data.items.0.eligible_km', '1.00')
        ->assertJsonPath('data.items.0.amount', '10.00')
        ->assertJsonPath('data.items.0.monthly_km_limit', '1.00');

    selfTravelRoute($org['empA'], '2026-11-21');
    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', selfTravelPayload('2026-11-21'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});

it('refuses self travelling while the route is open or has no gps distance', function () {
    $org = seedOrg();
    $org['empA']->update(['travel_km_rate' => 8, 'monthly_km_limit' => 100]);
    selfTravelRoute($org['empA'], '2026-11-20', punchedOut: false);

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', selfTravelPayload('2026-11-20'))
        ->assertStatus(422);

    selfTravelRoute($org['empA'], '2026-11-21', withPoints: false);
    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', selfTravelPayload('2026-11-21'))
        ->assertStatus(422);

    $org['empA']->update(['travel_km_rate' => null]);
    $org['userA']->unsetRelation('employee');
    selfTravelRoute($org['empA'], '2026-11-19');
    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', selfTravelPayload('2026-11-19'))
        ->assertStatus(422);
});

it('blocks a second self travelling claim for the same date and allows it again after rejection', function () {
    $org = seedOrg();
    $date = '2026-11-20';
    $org['empA']->update(['travel_km_rate' => 8, 'monthly_km_limit' => 500]);
    selfTravelRoute($org['empA'], $date);

    $created = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', selfTravelPayload($date))
        ->assertCreated();
    $id = $created->json('data.id');

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', selfTravelPayload($date))
        ->assertStatus(422);
    expect(ExpenseSelfTravelDay::query()->where('user_id', $org['userA']->id)->count())->toBe(1);

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', [
            'action' => 'submit',
            'items' => json_encode([
                [
                    'expense_date' => $date,
                    'type' => 'travelling',
                    'travel_mode' => 'self',
                    'description' => 'First',
                ],
                [
                    'expense_date' => $date,
                    'type' => 'travelling',
                    'travel_mode' => 'self',
                    'description' => 'Second',
                ],
            ]),
        ])->assertStatus(422);

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', [
            'action' => 'submit',
            'items' => json_encode([
                [
                    'expense_date' => $date,
                    'type' => 'travelling',
                    'travel_mode' => 'public',
                    'amount' => 80,
                    'description' => 'Bus',
                    'travel_from' => 'Center',
                    'travel_to' => 'Village',
                ],
                [
                    'expense_date' => $date,
                    'type' => 'food',
                    'amount' => 40,
                    'description' => 'Lunch',
                ],
            ]),
        ])->assertCreated()
        ->assertJsonPath('data.total_amount', '120.00');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/reject', ['remarks' => 'Use the other day'])
        ->assertOk();

    expect(ExpenseSelfTravelDay::query()->where('expense_claim_id', $id)->count())->toBe(0);

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', selfTravelPayload($date))
        ->assertCreated();
});

it('stores a receipt only after the upload succeeds and lets a manager set mobilizer travel rates', function () {
    $org = seedOrg();
    $date = '2026-11-20';
    $org['empA']->update(['travel_km_rate' => 8, 'monthly_km_limit' => 500]);
    selfTravelRoute($org['empA'], $date);

    $uploaded = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses/receipts', [
            'receipt' => UploadedFile::fake()->image('bill.jpg'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'uploaded');

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', [
            'action' => 'submit',
            'items' => json_encode([[
                'expense_date' => $date,
                'type' => 'travelling',
                'travel_mode' => 'self',
                'description' => 'With bill',
                'receipt_token' => $uploaded->json('data.token'),
            ]]),
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.has_receipt', true);

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/manager/employees', [
            'center_id' => $org['centerA']->id,
            'full_name' => 'Blocked',
            'mobile' => '9123456700',
            'staff_role' => 'mobilizer',
            'travel_km_rate' => 4,
            'monthly_km_limit' => 100,
        ])->assertForbidden();

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/employees', [
            'center_id' => $org['centerA']->id,
            'full_name' => 'Route Mobilizer',
            'mobile' => '9123456701',
            'staff_role' => 'mobilizer',
            'travel_km_rate' => 7.5,
            'monthly_km_limit' => 250,
        ])
        ->assertCreated()
        ->assertJsonPath('data.travel_km_rate', '7.50')
        ->assertJsonPath('data.monthly_km_limit', '250.00');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/employees', [
            'center_id' => $org['centerA']->id,
            'full_name' => 'Missing Rates',
            'mobile' => '9123456702',
            'staff_role' => 'mobilizer',
        ])->assertStatus(422);
});

it('shows travel from and to after a travel category is chosen', function () {
    $org = seedOrg();
    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);

    Livewire::actingAs($org['centerManager'])
        ->test(CreateExpenseClaim::class)
        ->assertDontSee('Travel from')
        ->fillForm([
            'items' => [[
                'expense_date' => '2026-11-20',
                'type' => 'travel_conveyance',
                'description' => 'Village visit',
            ]],
        ])
        ->assertSee('Self Travelling')
        ->assertSee('Public Transport')
        ->assertSee('Travel from');
});
