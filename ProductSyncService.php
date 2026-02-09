<?php

namespace Amplify\ErpApi;

use Amplify\ErpApi\Facades\ErpApi;
use Amplify\ErpApi\Jobs\ProductSyncJob;
use Amplify\ErpApi\Wrappers\ProductSync as ProductSyncWrapper;
use Amplify\System\Backend\Models\Brand;
use Amplify\System\Backend\Models\Manufacturer;
use Amplify\System\Backend\Models\Product;
use Amplify\System\Backend\Models\ProductSync as ProductSyncModel;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
                Log::error(now()->format('r') . ' Product Sync Exception : ' . $exception->getMessage());
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

        match ($productSync->update_action) {
            self::ACTION_DELETE => $this->archiveDeletedItem($productSync),
            self::ACTION_CHANGE, self::ACTION_UPDATE => $this->updateItemData($productSync),
            self::ACTION_NEW => $this->createNewItem($productSync),
            default => null,
        };
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

    private function getProductDescription(ProductSyncModel $productSync): string
    {
        if (config('amplify.client_code') === 'RHS') {
            return $productSync->rhs_parts_note ?? '';
        }

        return "{$productSync->description_1} {$productSync->description_2}";
    }

    /**
     * @param ProductSyncModel $productSync
     * @return void
     */
    private function updateItemData(ProductSyncModel $productSync): void
    {
        try {
            $items = Product::productCode($productSync->item_number)->get();

            if ($items->isEmpty()) {
                throw (new ModelNotFoundException())->setModel(Product::class, 'code:' . $productSync->item_number);
            }

            foreach ($items as $item) {
                $manufacturerId = $this->getManufacturerId($productSync);
                $brand = $this->getBrand($productSync);

                $data = [
                    'is_updated' => true,
                    'uom' => $productSync->unit_of_measure,
                    'description' => $this->getProductDescription($productSync),
                    'short_description' => $productSync->description_1 ?? ' ',
                    'msrp' => $productSync->list_price ?? null,
                    'vendornum' => $productSync->primary_vendor ?? null,
                    'brand_name' => $brand?->title ?? null,
                    'brand_id' => $brand?->id ?? null,
                    'allow_back_order' => $productSync->allow_backorder !== null ? $productSync->allow_backorder : null,
                    'manufacturer' => !empty($productSync->manufacturer) ? $productSync->manufacturer : ($productSync->standard_part_number ?? null),
                ];

                if (!empty($manufacturerId)) {
                    $data['manufacturer_id'] = $manufacturerId;
                }

                $item->update($data);
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
        try {
            $item = new Product;
            $manufacturerId = $this->getManufacturerId($productSync);
            $manufacturerPartNo = !empty($productSync->manufacturer) ? $productSync->manufacturer : ($productSync->standard_part_number ?? null);

            $item->product_name = $productSync->description_1;
            $item->product_code = $productSync->item_number;
            $item->description = $this->getProductDescription($productSync);
            $item->short_description = $productSync->description_1;
            $item->msrp = $productSync->list_price ?? null;
            $item->vendornum = $productSync->primary_vendor ?? null;
            $item->brand_name = $brand?->title ?? null;
            $item->brand_id = $brand?->id ?? null;
            $item->is_new = true;
            $item->uom = $productSync->unit_of_measure;
            $item->manufacturer = $manufacturerPartNo;
            $item->manufacturer_id = $manufacturerId;
            $item->status = 'draft';
            $item->allow_back_order = $productSync->allow_backorder !== null ? $productSync->allow_backorder : null;
            $item->user_id = $this->approveId;

            $item->save();

            $this->setProcessedFlag($productSync);
        } catch (\Throwable $th) {
            Log::debug($th);

            $this->setProcessedFlag($productSync, $th->getMessage());
        }
    }

    private function getManufacturerId(ProductSyncModel $productSync)
    {
        if (!empty($productSync->brand)) {
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

    private function getBrand(ProductSyncModel $productSync): ?Brand
    {
        if (!empty($productSync->brand)) {

            $slug = preg_replace_callback('/(^|-)([a-z])/',
                fn($matches) => $matches[1] . strtoupper($matches[2]),
                Str::slug($productSync->brand)
            );

            return Brand::firstOrCreate(['title' => $productSync->brand],
                [
                    'title' => $productSync->brand,
                    'slug' => $slug,
                    'image' => asset(config('amplify.frontend.fallback_image_path')),
                ]);

        }

        return null;
    }
}
