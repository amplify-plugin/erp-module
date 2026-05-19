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

    private int $parentChunkSize = 1000;

    private int $childDispatchChunk = 100;

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
        if (empty($this->ids)) {
            return;
        }

        $firstId = array_shift($this->ids);

        // Case: process all rows matching the criteria by creating many small child jobs
        if ($firstId === 'all') {
            $userId = $this->userId; // capture for closure

            ProductSync::select('id')
                ->where('is_processed', false)
                ->whereNotNull('error')
                ->orderBy('id')
                ->chunkById($this->parentChunkSize, function ($rows) use ($userId) {
                    $ids = $rows->pluck('id')->values()->all();
                    if (empty($ids)) {
                        return;
                    }

                    // Dispatch a child PromptProductSyncJob that will handle this small set of ids.
                    static::dispatch($ids, $userId)->onQueue('worker');
                });

            return;
        }

        // remaining
        $idsToProcess = array_merge([$firstId], $this->ids);

        foreach (array_chunk($idsToProcess, $this->childDispatchChunk) as $chunk) {
            foreach ($chunk as $id) {
                ProductSyncJob::dispatch($id, $this->userId)->onQueue('worker');
            }
        }
    }
}
