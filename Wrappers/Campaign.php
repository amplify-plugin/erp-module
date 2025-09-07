<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Collections\CampaignDetailCollection;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class Campaign
 *
 * @property string $Promoid
 * @property string $BegDate
 * @property string $EndDate
 * @property string $ShortDesc
 * @property string $Hashtag
 * @property string $LongDesc
 * @property string $ImagePath
 * @property string $Clearance
 * @property string $Online
 * @property string $Inside
 * @property string $Print
 * @property string $Sort
 * @property string $Private
 * @property CampaignDetailCollection $CampaignDetail
 */
class Campaign extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = ['Promoid', 'BegDate', 'EndDate', 'ShortDesc', 'Hashtag', 'LongDesc', 'ImagePath', 'Clearance', 'Online', 'Inside', 'Print', 'Sort', 'Private', 'CampaignDetail'];
}
