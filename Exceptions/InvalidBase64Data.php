<?php

namespace Amplify\ErpApi\Exceptions;

use Exception;

class InvalidBase64Data extends Exception
{
    public static function create(): self
    {
        return new static('Invalid base64 data provided');
    }
}
