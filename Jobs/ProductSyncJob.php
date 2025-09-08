<?php

namespace Amplify\ErpApi\Jobs;

use Amplify\System\Backend\Models\ProductSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProductSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $productSyncId;

    public $approverId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id, $user_id)
    {
        $this->productSyncId = $id;
        $this->approverId = $user_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($productSync = ProductSync::findOrFail($this->productSyncId)) {
            \ErpApi::updateProductWithSyncData($productSync, $this->approverId);
        }
    }
}
