<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class TermsType
 *
 * @property string $TermsType
 */
class TermsType extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    /**
     * @var mixed|null
     */
    protected array $fillable = [
        'TermsType',
    ];

    public function isCreditCardOnly(): bool
    {
        return in_array(strtoupper($this->TermsType), ['CRCD', 'COD']);
    }

    public function isACHOnly(): bool
    {
        return strtoupper($this->TermsType) === 'CIA';
    }

    public function isBlocked(): bool
    {
        return strtoupper($this->TermsType) === 'MAN';
    }
}
