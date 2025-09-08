<?php

namespace Amplify\ErpApi\Interfaces;

use Amplify\System\Backend\Models\ProductSync;

interface ProductSyncInterface
{
    /*
    |--------------------------------------------------------------------------
    | SYNCHRONIZATION FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function storeProductSyncOnModel(array $filters): array;

    /**
     * @return mixed
     */
    public function dispatchProductSyncJob($id, $approveId = null);

    /**
     * @return void
     */
    public function updateProductWithSyncData(ProductSync $productSync, ?int $approveId = null);
}
