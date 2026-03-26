<?php

namespace Amplify\ErpApi\Jobs;

use Amplify\System\Backend\Models\ProductSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PromptProductSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $ids = [], public ?int $userId = null)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!empty($this->ids)) {

            $firstId = array_shift($this->ids);

            $query = ProductSync::select('id')
                ->when($firstId == 'all', fn ($query)  => $query->where('is_processed', '=', false)->whereNotNull('error'))
                ->when($firstId !== 'all', fn ($query) => $query->whereIn('id', [$firstId, ...$this->ids]));

            foreach ($query->cursor() as $productSync) {
                ProductSyncJob::dispatch($productSync->id, $this->userId)->onQueue('worker');
            }
        }
    }
}
