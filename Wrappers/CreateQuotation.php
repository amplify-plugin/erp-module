<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property string $OrderNumber
 * @property string $TotalOrderValue
 * @property string $SalesTaxAmount
 * @property string $FreightAmount
 */
class CreateQuotation extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    /**
     * @var array|string[]
     */
    protected array $fillable = [
        'OrderNumber',
        'TotalOrderValue',
        'SalesTaxAmount',
        'FreightAmount',
    ];
}
