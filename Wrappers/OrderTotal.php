<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property string|int $OrderNumber
 * @property float $TotalOrderValue
 * @property float $SalesTaxAmount
 * @property float $FreightAmount
 * @property array $FreightRate
 * @property float $HazMatCharge
 */
class OrderTotal extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'OrderNumber',
        'TotalOrderValue',
        'SalesTaxAmount',
        'FreightAmount',
        'FreightRate',
        'HazMatCharge',
        'WireTrasnsferFee',
    ];
}
