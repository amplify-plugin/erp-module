<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property $AuthorizationNumber
 * @property $OnAccountDocument
 * @property $OnAccountAmount
 * @property $Receipt
 * @property $DistributedAmount
 * @property $Message
 * @property $Token
 */
class CreatePayment extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = ['AuthorizationNumber', 'OnAccountDocument', 'OnAccountAmount', 'Receipt', 'DistributedAmount', 'Message', 'Token'];
}
