<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class CampaignDetail
 *
 * @property string $Promoid
 * @property string $Item
 * @property string $ItemDescription
 * @property string $ExtDescription
 * @property string $Attributes
 * @property string $ItemID
 * @property string $ItemImagePath
 * @property string $Price
 * @property string $PriceUM
 * @property string $Sort
 */
class CampaignDetail extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = ['Promoid', 'Item', 'ItemDescription', 'ExtDescription', 'Attributes', 'ItemID', 'ItemImagePath', 'Price', 'PriceUM', 'Sort'];
}
