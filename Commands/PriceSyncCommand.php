<?php

namespace Amplify\ErpApi\Commands;

use Amplify\ErpApi\Jobs\PriceSyncJob;
use Amplify\System\Backend\Models\Product;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PriceSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amplify:erp-price-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Product Price sync from ERP APIs';

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): int
    {
        try {

            $startTime = now();

            $range = Product::selectRaw('MIN(`id`) as first_id, MAX(`id`) as last_id')->first();

            $firstId = $range->first_id;
            $lastId = $range->last_id;

            if ($firstId == $lastId) {
                $this->error("No Products Found");
            }

            PriceSyncJob::dispatch($firstId, $lastId, 20, $startTime);

            return self::SUCCESS;

        } catch (\Exception $exception) {

            $this->error(now()->format('r') . ' Product Sync Exception: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
