<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property $ShipToNumber
 * @property $ShipToName
 * @property $ShipToCountryCode
 * @property $ShipToAddress1
 * @property $ShipToAddress2
 * @property $ShipToAddress3
 * @property $ShipToCity
 * @property $ShipToState
 * @property $ShipToZipCode
 * @property $ShipToPhoneNumber
 * @property $ShipToContact
 * @property $ShipToWarehouse
 * @property $BackorderCode
 * @property $CarrierCode
 * @property $PoRequired
 */
class ShippingLocation extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'ShipToNumber',
        'ShipToName',
        'ShipToCountryCode',
        'ShipToAddress1',
        'ShipToAddress2',
        'ShipToAddress3',
        'ShipToCity',
        'ShipToState',
        'ShipToZipCode',
        'ShipToPhoneNumber',
        'ShipToContact',
        'ShipToWarehouse',
        'BackorderCode',
        'CarrierCode',
        'PoRequired',
    ];
}
