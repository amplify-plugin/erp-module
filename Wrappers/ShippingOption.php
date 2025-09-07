<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property string $CarrierCode
 * @property string $CarrierDescription
 * @property string $Driver
 * @property int $InternalId
 * @property string $Name
 * @property string $Value
 */
class ShippingOption extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'InternalId',
        'CarrierCode',
        'CarrierDescription',
        'Driver',
        'Name',
        'Value',
    ];
}
