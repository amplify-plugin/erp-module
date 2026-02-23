<?php

namespace Amplify\ErpApi\Commands;

use Amplify\ErpApi\Jobs\ContactProfileSyncJob;
use Amplify\System\Backend\Models\Contact;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CsdErpContactSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amplify:csd-erp-contact-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Contact Sync from CSD ERP API';

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): int
    {
        try {

            if (config('amplify.erp.default') !== 'csd-erp') {
                throw new \ErrorException("The operation is not allowed for this " . config('amplify.erp.default') . " ERP.");
            }

            foreach (Contact::whereNull('contact_code')->orWhereRaw('contact_code NOT REGEXP ?', ['^[0-9]+$'])->cursor() as $contact) {
                ContactProfileSyncJob::dispatch($contact->toArray())->onQueue('worker');
            }

            return self::SUCCESS;

        } catch (\Exception $exception) {

            Log::error($exception);

            return self::FAILURE;
        }
    }
}
