<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property string $Name
 * @property string $Address1
 * @property string $Address2
 * @property string $Address3
 * @property string $City
 * @property string $State
 * @property string $ZipCode
 * @property bool $Status
 * @property string $Response
 * @property string $Message
 * @property mixed $Details
 * @property string $CountryCode
 * @property string|integer $Reference
 */
class ShippingLocationValidation extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'Name',
        'Address1',
        'Address2',
        'Address3',
        'CountryCode',
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
