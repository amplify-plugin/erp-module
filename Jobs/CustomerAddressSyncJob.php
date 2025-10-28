<?php

namespace Amplify\ErpApi\Jobs;

use Amplify\ErpApi\Facades\ErpApi;
use Amplify\System\Backend\Models\CustomerAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CustomerAddressSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public CustomerAddress $customerAddress;

    /**
     * Create a new job instance.
     */
    public function __construct(array $address_data = [])
    {
        $this->customerAddress = CustomerAddress::findOrFail($address_data['id']);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $attributes = $this->customerAddress->toArray();

        $customer = $this->customerAddress->customer;

        $attributes['customer_number'] = $customer->erp_id;

        ErpApi::createCustomerShippingLocation($attributes);
    }
}
