<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class CreateCustomer
 *
 * @property string|null $Message
 * @property string $CustomerNumber
 * @property string $ArCustomerNumber
 * @property string $CustomerName
 * @property string $CustomerCountry
 * @property string $CustomerAddress1
 * @property string $CustomerAddress2
 * @property string $CustomerAddress3
 * @property string $CustomerCity
 * @property string $CustomerState
 * @property string $CustomerZipCode
 * @property string $CustomerEmail
 * @property string $CustomerPhone
 * @property string $CustomerContact
 * @property string $DefaultShipTo
 * @property string $DefaultWarehouse
 * @property string $CarrierCode
 * @property string $PriceList
 * @property string $BackorderCode
 * @property string $CustomerClass
 * @property string $SuspendCode
 * @property string $AllowArPayments
 * @property string $CreditCardOnly
 * @property float $FreightOptionAmount
 * @property string $PoRequired
 * @property string $SalesPersonCode
 * @property string $SalesPersonName
 * @property string $SalesPersonEmail
 * @property string $ProductRestriction
 * @property mixed|null $ShipVias
 * @property string $WrittenIndustry
 * @property float $OTShipPrice
 */
class Customer extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = ['Message', 'CustomerNumber', 'ArCustomerNumber', 'CustomerName', 'CustomerCountry', 'CustomerAddress1',
        'CustomerAddress2', 'CustomerAddress3', 'CustomerCity', 'CustomerState', 'CustomerZipCode', 'CustomerEmail', 'CustomerPhone',
        'CustomerContact', 'DefaultShipTo', 'DefaultWarehouse', 'CarrierCode', 'PriceList', 'BackorderCode', 'CustomerClass', 'ShipVias',
        'SuspendCode', 'AllowArPayments', 'CreditCardOnly', 'FreightOptionAmount', 'PoRequired', 'SalesPersonCode', 'SalesPersonName',
        'SalesPersonEmail', 'ProductRestriction', 'WrittenIndustry', 'OTShipPrice',
    ];
}
