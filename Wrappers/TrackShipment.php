<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class TrackShipment
 *
 * @property mixed|null $OrderNumber
 * @property mixed|null $TrackerNo
 * @property string|null $ShipViaType
 */
class TrackShipment extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    /**
     * @var mixed|null
     */
    protected array $fillable = [
        'OrderNumber',
        'TrackerNo',
        'ShipViaType',
    ];
}
