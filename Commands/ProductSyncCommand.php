<?php

namespace Amplify\ErpApi\Commands;

use Amplify\ErpApi\Jobs\StoreErpProductSyncDataJob;
use Amplify\System\Backend\Models\Event;
use Amplify\System\Events\ProductSynced;
use Amplify\System\Factories\NotificationFactory;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProductSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amplify:erp-product-sync 
                            {--updates-only=Y} 
                            {--process-updates=N} 
                            {--auto-update=N} 
                            {--limit=null}';

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

        if (config('amplify.schedule.catalog_sync_enabled', false) == false) {
            $this->info(now()->format('r') . ' Product Sync Disabled.');
            return self::SUCCESS;
        }

        $params = [
            'updates_only' => $this->option('updates-only') == 'Y' ? 'Y' : 'N',
            'process_updates' => $this->option('process-updates') == 'N' ? 'N' : 'Y',
            'limit' => $this->option('limit') ? $this->option('limit') : null,
            'auto_update' => $this->option('auto-update') == 'N' ? 'N' : 'Y',
            'restart_point' => null
        ];

        try {
            $startTime = now()->format('Y-m-d H:i:s');

            Storage::disk('local')->makeDirectory('product-sync');

            StoreErpProductSyncDataJob::dispatch($startTime, 1, $params);

            return self::SUCCESS;

        } catch (\Exception $exception) {

            $this->error(now()->format('r') . ' Product Sync Exception: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
