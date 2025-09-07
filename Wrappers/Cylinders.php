<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property string|null $Cylinder
 * @property string|null $Beginning
 * @property string|null $Delivered
 * @property string|null $Returned
 * @property string|null $Balance
 * @property string|null $LastDelivery
 * @property string|null $LastReturned
 */
class Cylinders extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'Cylinder',
        'Beginning',
        'Delivered',
        'Returned',
        'Balance',
        'LastDelivery',
        'LastReturned',
    ];
}
