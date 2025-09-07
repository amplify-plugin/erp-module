<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property $BoType
 * @property $CommentFl
 * @property $ContNo
 * @property $Cubes
 * @property $DueDate
 * @property $EnterDate
 * @property $ExpShipDate
 * @property $LeadOverTy
 * @property $LineNo
 * @property $NetAmt
 * @property $NetRcv
 * @property $NonStockTy
 * @property $Price
 * @property $PrintFl
 * @property $ProdCat
 * @property $ProdCatDesc
 * @property $ProdDesc
 * @property $ProdDesc2
 * @property $ProdLine
 * @property $QtyOrd
 * @property $QtyRcv
 * @property $QtyUnAvail
 * @property $RcvCost
 * @property $ReasUnavTy
 * @property $ReqProd
 * @property $ReqShipDt
 * @property $ShipProd
 * @property $StatusType
 * @property $StkQtyOrd
 * @property $StkQtyRcv
 * @property $TallyFl
 * @property $TrackNo
 * @property $Unit
 * @property $UnitConv
 * @property $VaFakeProdFl
 * @property $Weight
 * @property $RcvUnAvailFl
 * @property $SortFld
 */
class OrderPODetails extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'BoType',
        'CommentFl',
        'ContNo',
        'Cubes',
        'DueDate',
        'EnterDate',
        'ExpShipDate',
        'LeadOverTy',
        'LineNo',
        'NetAmt',
        'NetRcv',
        'NonStockTy',
        'Price',
        'PrintFl',
        'ProdCat',
        'ProdCatDesc',
        'ProdDesc',
        'ProdDesc2',
        'ProdLine',
        'QtyOrd',
        'QtyRcv',
        'QtyUnAvail',
        'RcvCost',
        'ReasUnavTy',
        'ReqProd',
        'ReqShipDt',
        'ShipProd',
        'StatusType',
        'StkQtyOrd',
        'StkQtyRcv',
        'TallyFl',
        'TrackNo',
        'Unit',
        'UnitConv',
        'VaFakeProdFl',
        'Weight',
        'RcvUnAvailFl',
        'SortFld',
    ];
}
