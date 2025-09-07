<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @property $Subject
 * @property $Date
 * @property $NoteNum
 * @property $Type
 * @property $Editable
 * @property $Note
 * @property $Secureflag
 */
class OrderNote extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = ['Subject', 'Date', 'NoteNum', 'Type', 'Editable', 'Note', 'Secureflag'];
}
