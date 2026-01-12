<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class Document
 *
 * @property string $DocumentType
 * @property \Illuminate\Http\File $File
 * @property string $EntityName
 */
class Document extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = ['DocumentType', 'File', 'EntityName'];
}
