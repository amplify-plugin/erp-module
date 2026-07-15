<?php

namespace Amplify\ErpApi\Jobs;

use Amplify\ErpApi\Facades\ErpApi;
use Amplify\System\Backend\Models\Contact;
use Amplify\System\Backend\Models\Event;
use Amplify\System\Factories\NotificationFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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
        $customer_number = $this->contact->customer?->erp_id;

        $attributes = $this->contact->toArray();
        $attributes['action'] = empty($attributes['contact_code']) ? 'add' : 'chg';
        $attributes['customer_number'] = $customer_number;
        $attributes['account_title_code'] = $this->contact->accountTitle?->code ?? null;

        $erpContactCollection = ErpApi::getContactList(['customer_number' => $customer_number]);

        if ($erpContactCollection->isNotEmpty()) {
            if ($erpContactData = $erpContactCollection->first(fn($item) => strcasecmp($item->ContactEmail, $this->contact->email) === 0)) {

                /**
                 * @custom steven hook for first signup call
                 */
                if (config('amplify.client_code') == 'STV' && !$this->contact->enabled && $this->contact->enabled_at == null) {

                    $this->contact->update([
                        'contact_code' => $erpContactData->ContactNumber,
                        'synced_at' => now(),
                        'enabled' => true,
                        'enabled_at' => now()
                    ]);

                    NotificationFactory::callif(
                        config('amplify.security.request_account_verification_method') == 'backend'
                        || config('amplify.security.new_retail_customer_verification_method') == 'backend',
                        Event::CONTACT_ACCOUNT_REQUEST_ACCEPTED, [
                        'contact_id' => $this->contact->id,
                        'customer_id' => $this->contact->customer_id,
                    ]);

                    return;
                }

                $this->contact->update(['contact_code' => $erpContactData->ContactNumber, 'synced_at' => now()]);
                return;
            }
        }

        $erpContactData = ErpApi::createUpdateContact($attributes);
        if (!empty($erpContactData->ContactNumber)) {
            $this->contact->update(['contact_code' => $erpContactData->ContactNumber, 'synced_at' => now()]);
        }
    }
}
