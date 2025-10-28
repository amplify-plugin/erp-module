<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property $ItemNumber
 * @property $WarehouseID
 * @property $Price
 * @property $ListPrice
 * @property $StandardPrice
 * @property $QtyPrice_1
 * @property $QtyBreak_1
 * @property $QtyPrice_2
 * @property $QtyBreak_2
 * @property $QtyPrice_3
 * @property $QtyBreak_3
 * @property $QtyPrice_4
 * @property $QtyBreak_4
 * @property $QtyPrice_5
 * @property $QtyBreak_5
 * @property $QtyPrice_6
 * @property $QtyBreak_6
 * @property $ExtendedPrice
 * @property $OrderPrice
 * @property $UnitOfMeasure
 * @property $PricingUnitOfMeasure
 * @property $DefaultSellingUnitOfMeasure
 * @property $AverageLeadTime
 * @property $QuantityAvailable
 * @property $QuantityOnOrder
 * @property WarehouseCollection $Warehouses
 * @property $OwnTruckOnly
 * @property bool $QtyBreakExist
 * @property null|float $MinOrderQuantity
 * @property null|float $DiscountAmount
 */
class ProductPriceAvailability extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'ItemNumber',
        'WarehouseID',
        'Price',
        'ListPrice',
        'StandardPrice',
        'QtyBreakExist',
        'QtyPrice_1',
        'QtyBreak_1',
        'QtyPrice_2',
        'QtyBreak_2',
        'QtyPrice_3',
        'QtyBreak_3',
        'QtyPrice_4',
        'QtyBreak_4',
        'QtyPrice_5',
        'QtyBreak_5',
        'QtyPrice_6',
        'QtyBreak_6',
        'ExtendedPrice',
        'OrderPrice',
        'UnitOfMeasure',
        'PricingUnitOfMeasure',
        'DefaultSellingUnitOfMeasure',
        'AverageLeadTime',
        'QuantityAvailable',
        'QuantityOnOrder',
        'Warehouses',
        'OwnTruckOnly',
        'MinOrderQuantity',
        'DiscountAmount',
    ];
}
