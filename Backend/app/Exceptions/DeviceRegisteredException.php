<?php

namespace App\Exceptions;

use RuntimeException;

class DeviceRegisteredException extends RuntimeException
{
    public const CODE = 'DEVICE_REGISTERED';

    public const MESSAGE = 'This account is registered on another device. Please contact Admin for Device Change.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
