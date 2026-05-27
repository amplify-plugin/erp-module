<?php

namespace Amplify\ErpApi\Jobs;

use Amplify\System\Backend\Models\Event;
use Amplify\System\Events\ProductSynced;
use Amplify\System\Factories\NotificationFactory;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class StoreErpProductSyncDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public ?string $startTime = null, public int $iteration = 1, public array $options = [])
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        [$syncData, $restartPoint] = \ErpApi::storeProductSyncOnModel($this->options);

        if (!empty($syncData)) {
            Storage::disk('local')->put("product-sync/sync-data_{$this->iteration}.json", json_encode($syncData));
        }

        if (!empty($restartPoint)) {
            $next = $this->iteration + 1;
            static::dispatch($this->startTime, $next, array_merge($this->options, ['restart_point' => $restartPoint]));
            return;
        }

        $syncedFiles = Storage::disk('local')->files('product-sync');

        $syncedFiles = array_filter($syncedFiles, fn($f) => \str_ends_with($f, '.json'));

        $fullSyncedData = [];

        foreach ($syncedFiles as $file) {
            $fullSyncedData = array_merge($fullSyncedData, json_decode(file_get_contents(Storage::disk('local')->path($file)), true));
        }

        logger("full data", $fullSyncedData);

        NotificationFactory::call(Event::CATALOG_CHANGED, [
            '__started_at__' => CarbonImmutable::parse($this->startTime)
                ->format(config('amplify.basic.date_format', 'D MMM YYYY, HH:mm')),
            '__ended_at__' => now()
                ->format(config('amplify.basic.date_time_format', 'D MMM YYYY, HH:mm')),
            '__execution_date__' => now(config('app.timezone'))
                ->format(config('amplify.basic.date_format', 'D MMM YYYY, HH:mm')),
            'products' => array_map(fn($item) => $item['itemNumber'] ?? null, $fullSyncedData),
        ]);

        \event(new ProductSynced($fullSyncedData));

    }
}
