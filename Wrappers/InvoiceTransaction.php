<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class InvoiceTransaction
 *
 * @property string $TransactionDate
 * @property string $TransactionType
 * @property string $TransactionAmount
 * @property string $PaymentAmount
 * @property string $CashDiscountAmount
 * @property string $CheckNumber
 * @property string $AdjustmentNumber
 * @property string $OrderNumber
 * @property string $OrderSuffix
 * @property string $PurchaseOrderNumber
 */
class InvoiceTransaction extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = ['TransactionDate', 'TransactionType', 'TransactionAmount', 'PaymentAmount', 'CashDiscountAmount', 'CheckNumber', 'AdjustmentNumber',
        'OrderNumber', 'OrderSuffix', 'PurchaseOrderNumber',
    ];
}
