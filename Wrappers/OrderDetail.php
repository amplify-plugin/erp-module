<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property $LineNumber
 * @property $ItemNumber
 * @property $ItemType
 * @property $ItemDescription1
 * @property $ItemDescription2
 * @property $QuantityOrdered
 * @property $QuantityShipped
 * @property $QuantityBackordered
 * @property $UnitOfMeasure
 * @property $PricingUM
 * @property $ActualSellPrice
 * @property $TotalLineAmount
 * @property $ShipWhse
 * @property $ConvertedToOrder
 * @property $InHouseDeliveryDate
 * @property $LineShipVia
 * @property $LineFrtTerms
 * @property $LineFrtBillAcct
 * @property $DirectOrder
 */
class OrderDetail extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'LineNumber',
        'ItemNumber',
        'ItemType',
        'ItemDescription1',
        'ItemDescription2',
        'QuantityOrdered',
        'QuantityShipped',
        'QuantityBackordered',
        'UnitOfMeasure',
        'PricingUM',
        'ActualSellPrice',
        'TotalLineAmount',
        'ShipWhse',
        'ConvertedToOrder',
        'InHouseDeliveryDate',
        'PODetails',
        'TiedOrder',
        'LineShipVia',
        'LineFrtTerms',
        'LineFrtBillAcct',
        'DirectOrder',
    ];
}
