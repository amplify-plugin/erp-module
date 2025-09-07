<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property $CarrierCode
 * @property $CarrierDescription
 */
class ShippingMethod extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'CustomerNumber',
        'CarrierCode',
        'PoNumber',
        'ShipToAddress1',
        'ShipToAddress2',
        'ShipToAddress3',
        'ShipToCity',
        'ShipToState',
        'ShipToZipCode',
        'OrderType',
        'ReturnType',
        'WarehouseID',
        'Items',
    ];
}
