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

it('uses email as login id when org user login id is blank', function () {
    expect(LoginId::resolveFromEmail(null, 'director2@fieldtrack.local'))->toBe('director2@fieldtrack.local')
        ->and(LoginId::resolveFromEmail('  custom.dir  ', 'director2@fieldtrack.local'))->toBe('custom.dir');
});

it('requires email when org user login id is blank', function () {
    LoginId::resolveFromEmail('  ', '');
})->throws(ValidationException::class);

it('builds a default password from the last 4 digits of a mobile number', function () {
    expect(LoginId::defaultPasswordFromMobile('9876543210'))->toBe('3210')
        ->and(LoginId::defaultPasswordFromMobile('  9123456780  '))->toBe('6780')
        ->and(LoginId::defaultPasswordFromMobile('7796171633'))->toBe('1633');
});

it('builds the default password reset message', function () {
    expect(LoginId::defaultPasswordResetMessage('1633'))
        ->toBe('Password reset successfully. Default password: 1633');
});

it('rejects a duplicate login id', function () {
    seedOrg();
    LoginId::assertUnique('admin');
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
