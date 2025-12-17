<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property string|int $OrderNumber
 * @property float $TotalOrderValue
 * @property float $TotalLineAmount
 * @property float $SalesTaxAmount
 * @property float $FreightAmount
 * @property array $FreightRate
 * @property float $HazMatCharge
 * @property float $WireTrasnsferFee
 * @property \Illuminate\Support\Collection $OrderLines
 */
class OrderTotal extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'OrderNumber',
        'TotalLineAmount',
        'TotalOrderValue',
        'SalesTaxAmount',
        'FreightAmount',
        'FreightRate',
        'HazMatCharge',
        'WireTrasnsferFee',
        'OrderLines'
    ];
}
