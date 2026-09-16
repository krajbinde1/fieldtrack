<?php

use App\Support\LoginId;
use Illuminate\Validation\ValidationException;

it('uses mobile as login id when login id is blank', function () {
    expect(LoginId::resolve(null, '9876543210'))->toBe('9876543210')
        ->and(LoginId::resolve('  field.exec-1  ', '9876543210'))->toBe('field.exec-1');
});

it('requires mobile when login id is blank', function () {
    LoginId::resolve('  ', '');
})->throws(ValidationException::class);

it('rejects a duplicate login id', function () {
    seedOrg();
    LoginId::assertUnique('director');
})->throws(ValidationException::class);

it('lets a user sign in with a custom login id', function () {
    $org = seedOrg();
    $org['userA']->update(['login_id' => 'field.exec-1']);

    $this->postJson('/api/login', [
        'login_id' => 'field.exec-1',
        'password' => 'Employee@123',
        'device_id' => 'device-login-id',
    ])->assertOk()->assertJsonPath('user.login_id', 'field.exec-1');
});

it('does not change login id when employee mobile changes', function () {
    $org = seedOrg();
    $original = $org['userA']->login_id;

    $org['empA']->update(['mobile' => '9111111111']);

    expect($org['userA']->fresh()->login_id)->toBe($original)
        ->and($org['empA']->fresh()->mobile)->toBe('9111111111');
});
