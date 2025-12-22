<?php

namespace Amplify\ErpApi\Jobs;

use Amplify\System\Backend\Models\ProductSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProductSyncJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ProductSync $productSync;

    public $approverId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id, $user_id)
    {
        $this->productSync = ProductSync::find($id);

        $this->productSync->is_processing = true;
        $this->productSync->save();

        $this->approverId = $user_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \ErpApi::updateProductWithSyncData($this->productSync, $this->approverId);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->productSync->item_number;
    }
}
