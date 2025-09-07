<?php

namespace Amplify\ErpApi\Traits;

trait ApiWrapperTrait
{
    public function __construct($attributes)
    {
        parent::__construct($attributes);

        $this->boot();
    }

    private function boot()
    {
        array_walk($this->fillable, function ($property) {
            $this->attributes[$property] = null;
        });
    }
}
