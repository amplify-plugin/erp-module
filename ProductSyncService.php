<?php

namespace Amplify\ErpApi;

use Amplify\ErpApi\Facades\ErpApi;
use Amplify\ErpApi\Jobs\PromptProductSyncJob;
use Amplify\ErpApi\Wrappers\ProductSync as ProductSyncWrapper;
use Amplify\System\Backend\Jobs\GenerateProductSlugJob;
use Amplify\System\Backend\Models\Brand;
use Amplify\System\Backend\Models\Manufacturer;
use Amplify\System\Backend\Models\Product;
use Amplify\System\Backend\Models\ProductSync as ProductSyncModel;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
    public const ACTION_NEW = 'NEW';

    public const ACTION_UPDATE = 'UPDATE';

    public const ACTION_CHANGE = 'CHANGE';

    public const ACTION_DELETE = 'DELETE';

    public const DEFAULT_APPROVE_USER_ID = 1;

    private array $syncLogData = [];

    private int $approveId;

    /**
     * @throws Exception
     */
    public function storeProductSyncOnModel(array $filters): array
    {
        try {
            $products = ErpApi::getProductSync($filters);

            foreach ($products as $product) {
                $this->storeApiResponse($product);
            }

            if (isset($filters['auto_update']) && $filters['auto_update'] == 'Y' && !empty($this->syncLogData)) {
                PromptProductSyncJob::dispatch(
                    array_column($this->syncLogData, 'id'),
                    self::DEFAULT_APPROVE_USER_ID
                )->onQueue('worker');
            }

        } catch (Exception $exception) {
            throw new \Error($exception->getMessage(), 0, $exception);
        }

        return $this->syncLogData;
    }

    /***
     * @param ProductSyncModel $productSync
     * @param int|null $approveId
     * @return void
     */
    public function updateProductWithSyncData(ProductSyncModel $productSync, ?int $approveId = null): void
    {
        $this->approveId = $approveId ?? self::DEFAULT_APPROVE_USER_ID;

        match ($productSync->update_action) {
            self::ACTION_DELETE => $this->archiveDeletedItem($productSync),
            self::ACTION_CHANGE, self::ACTION_UPDATE => $this->updateItemData($productSync),
            self::ACTION_NEW => $this->createNewItem($productSync),
            default => null,
        };
    }

    public function storeApiResponse(ProductSyncWrapper $productSync): ?ProductSyncModel
    {
        $productSyncModel = new ProductSyncModel;

        $productSyncModel->payload = $productSync->getRawContent() ?? [];
        $productSyncModel->item_number = $productSync->ItemNumber;
        $productSyncModel->update_action = $productSync->UpdateAction;
        $productSyncModel->description_1 = $productSync->Description1;
        $productSyncModel->description_2 = $productSync->Description2;
        $productSyncModel->item_class = $productSync->ItemClass;
        $productSyncModel->price_class = $productSync->PriceClass;
        $productSyncModel->list_price = $productSync->ListPrice;
        $productSyncModel->unit_of_measure = $productSync->UnitOfMeasure;
        $productSyncModel->pricing_unit_of_measure = $productSync->PricingUnitOfMeasure;
        $productSyncModel->manufacturer = $productSync->Manufacturer;
        $productSyncModel->primary_vendor = $productSync->PrimaryVendor;
        $productSyncModel->standard_part_number = $productSync->StandardPartNumber;
        $productSyncModel->brand = $productSync->Brand;
        $productSyncModel->rhs_parts_note = $productSync->RHSpartscomNotes;
        $productSyncModel->is_processed = false;
        $productSyncModel->allow_backorder = $productSync->AllowBackOrder !== null ? $productSync->AllowBackOrder : null;

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
     * @param ProductSyncModel $productSync
     * @return void
     */
    private function archiveDeletedItem(ProductSyncModel $productSync): void
    {
        try {
            $items = Product::productCode($productSync->item_number)->get();

            if ($items->isEmpty()) {
                throw (new ModelNotFoundException())->setModel(Product::class, 'code:' . $productSync->item_number);
            }

            foreach ($items as $item) {
                $item->update([
                    'status' => 'archived',
                    'previous_status' => $item->status,
                    'archived_at' => now(),
                ]);
            }

            $this->setProcessedFlag($productSync);

        } catch (\Throwable $th) {
            Log::debug($th);

            $this->setProcessedFlag($productSync, $th->getMessage());
        }
    }

    /**
     * @param ProductSyncModel $productSync
     * @return void
     */
    private function updateItemData(ProductSyncModel $productSync): void
    {
        $oldProductCode = $productSync->payload["AdditionalData"] ?? null;
        $productCode = $oldProductCode ?? $productSync->item_number;

        try {
            $items = Product::productCode($productCode)->get();

            if ($items->isEmpty()) {

                Log::error("No products in table: {$productCode}");

                $productSync->update_action = self::ACTION_NEW;
                $productSync->save();

                $this->createNewItem($productSync);

                return;
            }

            $manufacturer = $this->getManufacturer($productSync->manufacturer);

            $brand = $this->getBrand($productSync->brand);

            foreach ($items as $item) {
                $item->product_name = match (config('amplify.erp.default')) {
                    'facts-erp' => $productSync->description_1,
                    default => "{$productSync->description_1} {$productSync->description_2}"
                };
                $item->product_code = $productSync->item_number;
                $item->msrp = $productSync->list_price ?? null;
                $item->selling_price = $productSync->list_price ?? null;
                $item->vendornum = $productSync->primary_vendor ?? null;
                $item->brand_name = $brand?->title ?? null;
                $item->brand_id = $brand?->getKey() ?? null;
                $item->manufacturer_id = $manufacturer?->getKey() ?? null;
                $item->is_updated = true;
                $item->uom = $productSync->unit_of_measure;
                $item->manufacturer = $productSync->standard_part_number ?? null;
                $item->allow_back_order = $productSync->allow_backorder !== null ? $productSync->allow_backorder : null;
                $item->save();
            }

            $this->setProcessedFlag($productSync);

        } catch (\Throwable $th) {
            Log::debug($th);

            $this->setProcessedFlag($productSync, $th->getMessage());
        }
    }

    /**
     * @param ProductSyncModel $productSync
     * @param string $error
     * @return void
     */
    private function setProcessedFlag(ProductSyncModel $productSync, string $error = ''): void
    {
        $changes = [
            'is_processing' => false,
            'is_processed' => true,
        ];

        if (!empty($error)) {
            $changes['error'] = $error;
        }

        $productSync->update($changes);
    }

    /**
     * @param ProductSyncModel $productSync
     * @return void
     */
    private function createNewItem(ProductSyncModel $productSync): void
    {
        $oldProductCode = $productSync->payload["AdditionalData"] ?? null;
        if (!empty($oldProductCode)) {
            // Check if product with old product code exists and update it instead of creating new one
            $existingProduct = Product::productCode($oldProductCode)->first();
            if ($existingProduct) {
                $productSync->update_action = self::ACTION_CHANGE;
                $productSync->save();
                $this->updateItemData($productSync);
                return;
            }
        }

        try {
            $item = new Product;

            $manufacturer = $this->getManufacturer($productSync->manufacturer);

            $brand = $this->getBrand($productSync->brand);

            $item->product_name = match (config('amplify.erp.default')) {
                'facts-erp' => $productSync->description_1,
                default => "{$productSync->description_1} {$productSync->description_2}"
            };
            $item->product_code = $productSync->item_number;
            $item->msrp = $productSync->list_price ?? null;
            $item->selling_price = $productSync->list_price ?? null;
            $item->vendornum = $productSync->primary_vendor ?? null;

            $item->brand_name = $brand?->title ?? null;
            $item->brand_id = $brand?->getKey() ?? null;

            $item->manufacturer_id = $manufacturer?->getKey() ?? null;
            $item->is_new = true;
            $item->uom = $productSync->unit_of_measure;
            $item->manufacturer = $productSync->standard_part_number ?? null;
            $item->status = config('amplify.pim.default_status', 'draft');
            $item->allow_back_order = $productSync->allow_backorder !== null ? $productSync->allow_backorder : null;
            $item->user_id = $this->approveId;

            $item->save();

            GenerateProductSlugJob::dispatch([$item->getKey()]);

            $this->setProcessedFlag($productSync);
        } catch (\Throwable $th) {
            Log::debug($th);

            $this->setProcessedFlag($productSync, $th->getMessage());
        }
    }

    private function getManufacturer(string $keyword = null): ?Manufacturer
    {
        if (empty($keyword)) {
            return null;
        }

        return Manufacturer::when(
            config('amplify.erp.default') == 'csd-erp',
            fn($query) => $query->where('code', '=', $keyword))
            ->when(
                config('amplify.erp.default') == 'facts-erp',
                fn($query) => $query->where('name', '=', $keyword)
            )->first();
    }

    private function getBrand(string $keyword = null): ?Brand
    {
        if (empty($keyword)) {
            return null;
        }

        return Brand::when(
            config('amplify.erp.default') == 'csd-erp',
            fn($query) => $query->where('slug', '=', $keyword))
            ->when(
                config('amplify.erp.default') == 'facts-erp',
                fn($query) => $query->where('title', '=', $keyword)
            )->first();

        //@TODO OLD Code don't remove
//        if (!empty($productSync->brand)) {
//
//            $slug = preg_replace_callback('/(^|-)([a-z])/',
//                fn($matches) => $matches[1] . strtoupper($matches[2]),
//                Str::slug($productSync->brand)
//            );
//
//            return Brand::firstOrCreate(['title' => $productSync->brand],
//                [
//                    'title' => $productSync->brand,
//                    'slug' => $slug,
//                    'image' => asset(config('amplify.frontend.fallback_image_path')),
//                ]);
//
//        }
//
//        return null;
    }
}
