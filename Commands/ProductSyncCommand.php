<?php

namespace Amplify\ErpApi\Commands;

use Amplify\System\Factories\NotificationFactory;
use App\Models\Event;
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
    protected $signature = 'product:sync {--updatesOnly=Y} {--processUpdates=N} {--limit=null}';

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
                'limit' => ! empty($this->option('limit')) ? $this->option('limit') : null,
            ];

            try {
                $syncData = \ErpApi::storeProductSyncOnModel($params);

                if (count($syncData) > 0) {
                    NotificationFactory::call(Event::CATALOG_CHANGED, $syncData);
                }

                $this->info(now()->format('r').'Product Sync Report : '.json_encode($syncData));

                return CommandAlias::SUCCESS;

            } catch (\Exception $exception) {

                $this->error(now()->format('r').' Product Sync Exception: '.$exception->getMessage());

                return CommandAlias::FAILURE;
            }

        }

        $this->info(now()->format('r').' Product Sync Disbaled.');

        return CommandAlias::SUCCESS;

    }
}
