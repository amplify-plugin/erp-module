<?php

namespace Amplify\ErpApi\Jobs;

use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Facades\ErpApi;
use Amplify\ErpApi\Wrappers\ProductPriceAvailability;
use Amplify\System\Backend\Models\Product;
use Amplify\System\Backend\Models\ProductAvailability;
use Amplify\System\Backend\Models\ProductSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class PriceSyncJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public int $firstId, public int $lastId, public int $chunk, public Carbon $startTime)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /**
         * @var Collection $products
         */
        $products = Product::where('id', '>=', $this->firstId)
            ->limit($this->chunk)
            ->where('status', '!=', 'archived')
            ->orderBy('id', 'ASC')
            ->get();

        $lastProduct = $products->last();

        $warehouses = ErpApi::getWarehouses([['enabled', '=', true]]);

        $warehousePair = $warehouses->pluck('InternalId', 'WarehouseNumber')->toArray();

        $payload = [
            'warehouse' => $warehouses->pluck('WarehouseNumber')->implode(','),
        ];

        $payload['items'] = $products->map(function ($product) {
            return [
                'item' => $product->product_code,
                'qty' => $product->min_order_qty ?? 1,
                'uom' => $product->uom
            ];
        })->toArray();

        $priceAvailabilities = ErpApi::getProductPriceAvailability($payload)
            ->groupBy('ItemNumber');

        $priceAvailabilities->each(function ($collection, $itemNumber) use ($products, $warehousePair) {

            $firstItem = $collection->first();
            Product::where('product_code', '=', $itemNumber)
                ->update([
                    'selling_price' => $firstItem->ListPrice,
                    'msrp' => $firstItem->StandardPrice,
                    'is_updated' => 1,
                ]);

            $collection->each(function ($item) use ($products, $warehousePair) {

                $product = $products->firstWhere('product_code', $item->ItemNumber);

                ProductAvailability::updateOrCreate(
                    [
                        'item_number' => $item->ItemNumber,
                        'warehouse_id' => $warehousePair[$item->WarehouseID] ?? null,
                    ],
                    [
                        'product_id' => $product->id,
                        'price' => $item->Price,
                        'list_price_1' => $item->ListPrice,
                        'list_price_2' => $item->ListPrice,
                        'list_price_3' => $item->ListPrice,
                        'list_price_4' => $item->ListPrice,
                        'list_price_5' => $item->ListPrice,
                        'suspended' => 0,
                        'status' => $product->status,
                        'allow_backorder' => $product->allow_back_order ?? false,
                        'standard_price' => $item->StandardPrice,
                        'extended_price' => $item->ExtendedPrice,
                        'order_price' => $item->OrderPrice,
                        'unit_of_measure' => $item->UnitOfMeasure,
                        'pricing_unit_of_measure' => $item->UnitOfMeasure,
                        'default_selling_unit_of_measure' => $item->UnitOfMeasure,
                        'quantity_available' => $item->QuantityAvailable,
                        'quantity_on_order' => $item->QuantityOnOrder,
                    ]);
            });

        });

        if ($lastProduct->getKey() < $this->lastId) {
            self::dispatch($lastProduct->getKey(), $this->lastId, $this->chunk, $this->startTime);
        } else {
            logger()->info("Erp pricing sync job completed in " . str_replace([' before', ' after'], '', now()->diffForHumans($this->startTime)));
        }
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "{$this->firstId}-{$this->lastId}";
    }
}
