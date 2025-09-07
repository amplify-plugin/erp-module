<?php

namespace Amplify\ErpApi\Jobs;

use Amplify\ErpApi\Facades\ErpApi;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ContactProfileSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $contact;

    /**
     * Create a new job instance.
     */
    public function __construct($contact_data)
    {
        $this->contact = Contact::findOrFail($contact_data['id']);
    }

    /**
     * Execute the job.
     *
     * @throws \ErrorException
     */
    public function handle(): void
    {
        $customer_number = $this->contact->customer?->customer_erp_id;

        $attributes = $this->contact->toArray();
        $attributes['action'] = empty($attributes['contact_code']) ? 'add' : 'chg';
        $attributes['customer_number'] = $customer_number;
        $attributes['account_title_code'] = $this->contact->accountTitle?->code ?? null;

        $erpContactCollection = ErpApi::getContactList(['customer_number' => $customer_number, 'name' => $this->contact->name]);

        if ($erpContactCollection->isNotEmpty()) {
            if ($erpContactData = $erpContactCollection->firstWhere('ContactEmail', $this->contact->email)) {
                $this->contact->update(['contact_code' => $erpContactData->ContactNumber, 'synced_at' => now()]);

                return;
            }
        }

        $erpContactData = ErpApi::createUpdateContact($attributes);
        if (! empty($erpContactData->ContactNumber)) {
            $this->contact->update(['contact_code' => $erpContactData->ContactNumber, 'synced_at' => now()]);
        }
    }
}
