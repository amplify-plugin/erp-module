<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property int $InternalId
 * @property int $WarehouseNumber
 * @property string $WarehouseName
 * @property string $WhsSeqCode
 * @property string|int $CompanyNumber
 * @property string|int $WhPricingLevel
 * @property string $WarehousePhone
 * @property string|int $WarehouseZip
 * @property string $WarehouseAddress
 * @property string $WarehouseEmail
 * @property bool $IsPickUpLocation
 * @property string $ShipVia
 */
class Warehouse extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'InternalId',
        'WarehouseNumber',
        'WarehouseName',
        'WhsSeqCode',
        'CompanyNumber',
        'WhPricingLevel',
        'WarehousePhone',
        'WarehouseZip',
        'WarehouseAddress',
        'WarehouseEmail',
        'IsPickUpLocation',
        'ShipVia',
    ];
}
