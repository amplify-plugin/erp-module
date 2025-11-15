<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property $ItemNumber
 * @property $UpdateAction
 * @property $SubAction
 * @property $Description1
 * @property $Description2
 * @property $ItemClass
 * @property $PriceClass
 * @property float $ListPrice
 * @property $UnitOfMeasure
 * @property $PricingUnitOfMeasure
 * @property $Manufacturer
 * @property $PrimaryVendor
 * @property $RHSpartscomNotes
 * @property $Brand
 */
class ProductSync extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'ItemNumber',
        'SubAction',
        'UpdateAction',
        'Description1',
        'Description2',
        'ItemClass',
        'PriceClass',
        'ListPrice',
        'UnitOfMeasure',
        'PricingUnitOfMeasure',
        'Manufacturer',
        'PrimaryVendor',
        'BrandName',
        'StandardPartNumber',
        'Brand',
        'RHSpartscomNotes',
        'ImagePath',
        'ItemID',
        'ItemGroup',
    ];
}
