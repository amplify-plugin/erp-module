<?php

namespace Amplify\ErpApi\Resolvers;

use Amplify\ErpApi\Interfaces\ProductSyncNameResolver;
use Amplify\System\Backend\Models\ProductSync as ProductSyncModel;

class DefaultProductSyncNameResolver implements ProductSyncNameResolver
{
    /**
     * {@inheritDoc}
     */
    public function handle(ProductSyncModel $productSync): string
    {
        return match (config('amplify.erp.default')) {
            'facts-erp' => $productSync->description_1,
            default => "{$productSync->description_1} {$productSync->description_2}"
        };
    }
}
