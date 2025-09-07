<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property $Name
 * @property $Address1
 * @property $Address2
 * @property $Address3
 * @property $City
 * @property $State
 * @property $ZipCode
 * @property $Status
 * @property $Response
 * @property $Message
 * @property $Details
 * @property $Reference
 */
class ShippingLocationValidation extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'Name',
        'Address1',
        'Address2',
        'Address3',
        'City',
        'State',
        'ZipCode',
        'Status',
        'Response',
        'Message',
        'Details',
        'Reference',
    ];
}
