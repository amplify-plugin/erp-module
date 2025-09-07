<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class ContactValidation
 *
 * @property string $ValidCombination
 * @property string $CustomerNumber
 * @property string $ContactNumber
 * @property string $EmailAddress
 * @property string $DefaultWarehouse
 * @property string $CustomerAddress
 * @property string $DefaultShipTo
 * @property string $CustomerName
 * @property string $CustomerCity
 * @property string $CustomerState
 * @property string $CustomerStreet
 * @property string $CustomerZipCode
 * @property string $CustomerCountry
 */
class ContactValidation extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'ValidCombination', 'CustomerNumber', 'CustomerName', 'CustomerCity', 'CustomerAddress',
        'CustomerState', 'CustomerStreet', 'CustomerZipCode', 'CustomerCountry',
        'ContactNumber', 'EmailAddress', 'DefaultWarehouse', 'DefaultShipTo'];
}
