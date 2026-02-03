<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class Contact
 *
 * @property string|null $Message
 * @property string $CustomerNumber
 * @property string $ContactNumber
 * @property string $ContactName
 * @property string $AccountTitle
 * @property string $AccountTitleCode
 * @property string $AccountTitleDesc
 * @property string $ContactPhone
 * @property string $ContactEmail
 * @property string $ContactAddress1
 * @property string $ContactAddress2
 * @property string $ContactCity
 * @property string $ContactState
 * @property string $ContactZipCode
 * @property string $ContactCountry
 * @property string $Comment
 */
class Contact extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'CustomerNumber', 'ContactNumber', 'ContactName', 'AccountTitle', 'AccountTitleCode', 'AccountTitleDesc',
        'ContactPhone', 'ContactEmail', 'ContactAddress1', 'ContactAddress2', 'ContactCity',
        'ContactState', 'ContactZipCode', 'Comment', 'Message'
    ];
}
