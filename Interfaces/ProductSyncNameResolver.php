<?php

namespace Amplify\ErpApi\Interfaces;

use Amplify\System\Backend\Models\ProductSync as ProductSyncModel;

interface ProductSyncNameResolver
{
    /**
     * Resolve the product name for the given product sync record.
     *
     * Bind your own implementation in the application service provider to
     * customise the product name without modifying the core package.
     *
     * @param ProductSyncModel $productSync
     * @return string
     */
    public function handle(ProductSyncModel $productSync): string;
}
