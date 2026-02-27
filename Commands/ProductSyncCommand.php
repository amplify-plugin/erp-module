<?php

namespace Amplify\ErpApi\Commands;

use Amplify\System\Backend\Models\Event;
use Amplify\System\Events\ProductSynced;
use Amplify\System\Factories\NotificationFactory;
use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ProductSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amplify:product-sync {--updatesOnly=Y} {--processUpdates=N} {--limit=null}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Product sync from ERP APIs';

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): int
    {

        if (config('amplify.schedule.catalog_sync_enabled', false)) {

            $params = [
                'updates_only' => $this->option('updatesOnly'),
                'process_updates' => $this->option('processUpdates'),
                'limit' => !empty($this->option('limit')) ? $this->option('limit') : null,
            ];

            try {
                $startTime = now(config('app.timezone'))
                    ->format(config('amplify.basic.date_time_format', 'D MMM YYYY, HH:mm'));

                $syncData = \ErpApi::storeProductSyncOnModel($params);

                $endTime = now(config('app.timezone'))
                    ->format(config('amplify.basic.date_time_format', 'D MMM YYYY, HH:mm'));

                NotificationFactory::call(Event::CATALOG_CHANGED, [
                    '__started_at__' => $startTime,
                    '__ended_at__' => $endTime,
                    '__execution_date__' => now(config('app.timezone'))
                        ->format(config('amplify.basic.date_format', 'D MMM YYYY, HH:mm')),
                    'products' => array_map(fn($item) => $item['itemNumber'] ?? null, $syncData),
                ]);

                \event(new ProductSynced($syncData));

                $this->info(now()->format('r') . 'Product Sync Report : ' . json_encode($syncData));

                return self::SUCCESS;

            } catch (\Exception $exception) {

                $this->error(now()->format('r') . ' Product Sync Exception: ' . $exception->getMessage());

                return self::FAILURE;
            }

        }

        $this->info(now()->format('r') . ' Product Sync Disabled.');

        return self::SUCCESS;

    }
}
