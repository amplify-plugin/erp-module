<?php

namespace Amplify\ErpApi;

use Amplify\ErpApi\Facades\ErpApi;
use Amplify\ErpApi\Jobs\ProductSyncJob;
use Amplify\ErpApi\Wrappers\ProductSync as ProductSyncWrapper;
use Amplify\System\Backend\Models\Manufacturer;
use Amplify\System\Backend\Models\Product;
use Amplify\System\Backend\Models\ProductSync as ProductSyncModel;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProductSyncService
{
    public const ACTION_NEW = 'NEW';

    public const ACTION_UPDATE = 'UPDATE';

    public const ACTION_CHANGE = 'CHANGE';

    public const ACTION_DELETE = 'DELETE';

    public const DEFAULT_APPROVE_USER_ID = 1;

    private array $syncLogData;

    private int $approveId;

    public function __construct()
    {
        $this->syncLogData = [];
    }

    /**
     * @throws Exception
     */
    public function storeProductSyncOnModel(array $filters): array
    {
        try {
            $products = ErpApi::getProductSync($filters);
            foreach ($products as $product) {
                $productSync = $this->storeApiResponse($product);

                if ($productSync->id && config('amplify.schedule.commands.product_sync.auto_update_enabled')) {
                    $this->updateProductWithSyncData($productSync);
                }
            }
        } catch (Exception $exception) {
            if (suppress_exception()) {
                Log::error(now()->format('r').' Product Sync Exception : '.$exception->getMessage());
            } else {
                throw new RuntimeException($exception->getMessage(), 500, $exception);
            }
        }

        return $this->syncLogData;
    }

    /**
     * @return void
     */
    public function dispatchProductSyncJob($id, $approveId = null)
    {
        ProductSyncJob::dispatch($id, $approveId);
    }

    /***
     * @param ProductSyncModel $productSync
     * @param int|null $approveId
     * @return void
     */
    public function updateProductWithSyncData(ProductSyncModel $productSync, ?int $approveId = null)
    {
        $this->approveId = $approveId ?? self::DEFAULT_APPROVE_USER_ID;

        if ($productSync->update_action === self::ACTION_DELETE) {
            $this->archiveDeletedItem($productSync);
        } elseif ($productSync->update_action === self::ACTION_UPDATE
            || $productSync->update_action == self::ACTION_CHANGE) {
            $this->updateItemData($productSync);
        } elseif ($productSync->update_action === self::ACTION_NEW) {
            $this->createNewItem($productSync);
        }
    }

    private function storeApiResponse(ProductSyncWrapper $productSync): ?ProductSyncModel
    {
        $productSyncModel = new ProductSyncModel;

        $productSyncModel->item_number = $productSync->ItemNumber ?? '';
        $productSyncModel->update_action = $productSync->UpdateAction ?? '';
        $productSyncModel->description_1 = $productSync->Description1 ?? '';
        $productSyncModel->description_2 = $productSync->Description2 ?? '';
        $productSyncModel->item_class = $productSync->ItemClass ?? '';
        $productSyncModel->price_class = $productSync->PriceClass ?? '';
        $productSyncModel->list_price = $productSync->ListPrice ?? null;
        $productSyncModel->unit_of_measure = $productSync->UnitOfMeasure ?? '';
        $productSyncModel->pricing_unit_of_measure = $productSync->PricingUnitOfMeasure ?? '';
        $productSyncModel->manufacturer = $productSync->Manufacturer ?? '';
        $productSyncModel->primary_vendor = $productSync->PrimaryVendor ?? '';
        $productSyncModel->standard_part_number = $productSync->StandardPartNumber ?? '';
        $productSyncModel->brand = $productSync->Brand ?? '';
        $productSyncModel->rhs_parts_note = $productSync->RHSpartscomNotes ?? '';
        $productSyncModel->is_processed = false;

        if ($productSyncModel->update_action != null || strlen($productSyncModel->update_action) > 0) {
            $productSyncModel->save();

            $this->syncLogData[] = [
                'id' => $productSyncModel->id,
                'itemNumber' => $productSyncModel->item_number,
            ];
        }

        return $productSyncModel;
    }

    /**
     * @return void
     */
    private function archiveDeletedItem(ProductSyncModel $productSync)
    {
        try {
            $item = Product::productCode($productSync->item_number)->firstOrFail();

            $item->update([
                'status' => 'archived',
                'previous_status' => $item->status,
                'archived_at' => now(),
            ]);

            $this->setProcessedFlag($productSync);
        } catch (\Throwable $th) {
            Log::error('Product Sync Delete Exception : '.$th->getMessage());
        }
    }

    private function getProductDescription(ProductSyncModel $productSync): string
    {
        if (config('amplify.basic.client_code') === 'RHS') {
            return $productSync->rhs_parts_note ?? '';
        }

        return "{$productSync->description_1} {$productSync->description_2}";
    }

    /**
     * @return void
     */
    private function updateItemData(ProductSyncModel $productSync)
    {
        try {
            $item = Product::productCode($productSync->item_number)->firstOrFail();
            $manufacturerId = $this->getManufacturerId($productSync);

            $data = [
                'is_updated' => true,
                'uom' => $productSync->unit_of_measure,
                'description' => $this->getProductDescription($productSync),
                'short_description' => $productSync->description_1 ?? ' ',
                'msrp' => $productSync->list_price ?? null,
                'manufacturer' => ! empty($productSync->manufacturer) ? $productSync->manufacturer : ($productSync->standard_part_number ?? null),
            ];

            if (! empty($manufacturerId)) {
                $data['manufacturer_id'] = $manufacturerId;
            }

            $item->update($data);

            $this->setProcessedFlag($productSync);
        } catch (\Throwable $th) {
            Log::error('Product Sync Update Exception : '.$th->getMessage());
        }
    }

    /**
     * @return void
     */
    private function setProcessedFlag(ProductSyncModel $productSync)
    {
        $productSync->update([
            'is_processed' => true,
        ]);
    }

    /**
     * @return void
     */
    private function createNewItem(ProductSyncModel $productSync)
    {
        try {
            $item = new Product;
            $manufacturerId = $this->getManufacturerId($productSync);
            $manufacturerPartNo = ! empty($productSync->manufacturer) ? $productSync->manufacturer : ($productSync->standard_part_number ?? null);

            $item->product_name = $productSync->description_1;
            $item->product_code = $productSync->item_number;
            $item->description = $this->getProductDescription($productSync);
            $item->short_description = $productSync->description_1;
            $item->msrp = $productSync->list_price ?? null;
            $item->is_new = true;
            $item->uom = $productSync->unit_of_measure;
            $item->manufacturer = $manufacturerPartNo;
            $item->manufacturer_id = $manufacturerId;
            $item->status = 'draft';
            $item->user_id = $this->approveId;

            $item->save();

            $this->setProcessedFlag($productSync);
        } catch (\Throwable $th) {
            Log::error('Line: '.$th->getLine());
            Log::error('Product Sync Create Exception : '.$th->getMessage());
        }
    }

    private function getManufacturerId(ProductSyncModel $productSync)
    {
        if (! empty($productSync->brand)) {
            $manufacturer = Manufacturer::where('name', $productSync->brand)->first();

            if (empty($brand)) {
                $manufacturer = Manufacturer::create([
                    'name' => $productSync->brand,
                ]);
            }

            return $manufacturer->id;
        }

        return null;
    }
}
