<?php

namespace Amplify\ErpApi\Jobs;

use Amplify\ErpApi\Facades\ErpApi;
use Amplify\ErpApi\Wrappers\ShippingLocation;
use Amplify\System\Backend\Models\Customer;
use Amplify\System\Backend\Models\CustomerAddress;
use Amplify\System\Backend\Models\Warehouse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class CustomerProfileSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var Customer
     */
    public $customer;

    /**
     * Create a new job instance.
     */
    public function __construct($customer_data)
    {
        $this->customer = Customer::findOrFail($customer_data['customer_id']);
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        if (config('amplify.client_code') != 'ACP') {
            $canParallel = (PHP_OS_FAMILY == 'Linux' && function_exists('pcntl_fork'));

            ($canParallel)
                ? $this->parallelProfileSync()
                : $this->sequentialProfileSync();

            // set last synced datetime
            $this->customer->refresh();
            $this->customer->synced_at = now();
            $this->customer->save();
        }
    }

    /**
     * syncCustomerInfo
     *
     * @return void
     */
    private function syncCustomerInfo()
    {

        $erpCustomer = ErpApi::getCustomerDetail(['customer_number' => $this->customer->customer_code]);
        $warehouse = Warehouse::where('code', $erpCustomer->DefaultWarehouse)->first();

        $customer_modified_data = [
            'ar_number' => $erpCustomer->ArCustomerNumber ?? null,
            'class' => $erpCustomer->CustomerClass ?? null,
            'shipto_address_code' => $erpCustomer->DefaultShipTo ?? null,
            'suspend_code' => $erpCustomer->SuspendCode ?? null,
            'warehouse_id' => ! empty($warehouse) ? $warehouse->id : null,
            'carrier_code' => $erpCustomer->CarrierCode ?? null,
            'business_contact' => $erpCustomer->SalesPersonEmail ?? null,
            'customer_po_required' => $erpCustomer->PoRequired == 'Y',
            'allow_backorder' => $erpCustomer->BackorderCode == 'Y',
            'credit_card_only' => $erpCustomer->CreditCardOnly == 'Y',
            'free_shipment_amount' => empty($erpCustomer->FreightOptionAmount) ? null : filter_var($erpCustomer->FreightOptionAmount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'own_truck_ship_charge' => empty($erpCustomer->OTShipPrice) ? null : filter_var($erpCustomer->OTShipPrice, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'address_1' => $erpCustomer->CustomerAddress1 ?? null,
            'address_2' => $erpCustomer->CustomerAddress2 ?? null,
            'address_3' => $erpCustomer->CustomerAddress3 ?? null,
            'city' => $erpCustomer->CustomerCity ?? null,
            'state' => $erpCustomer->CustomerState ?? null,
            'zip_code' => $erpCustomer->CustomerZipCode ?? null,
            'country_code' => $erpCustomer->CustomerCountry ?? null,
        ];

        $this->customer->fill($customer_modified_data);

        $this->customer->save();
    }

    /**
     * syncShippingAddress
     *
     * @return void
     */
    private function syncShippingAddress()
    {
        try {
            $erp_shipping_addresses = ErpApi::getCustomerShippingLocationList(['customer_number' => $this->customer->erp_id]);
            $local_shipping_addresses = CustomerAddress::where('customer_id', $this->customer->id)->get();
            $ignore_to_delete = [];

            $erp_shipping_addresses->each(function (ShippingLocation $erp_ship_address) use (&$local_shipping_addresses, &$ignore_to_delete) {
                $erp_address_mapped_data = [
                    'customer_id' => $this->customer->id,
                    'address_code' => $erp_ship_address->ShipToNumber ?? null,
                    'address_name' => $erp_ship_address->ShipToName ?? null,
                    'address_1' => $erp_ship_address->ShipToAddress1 ?? null,
                    'address_2' => $erp_ship_address->ShipToAddress2 ?? null,
                    'address_3' => $erp_ship_address->ShipToAddress3 ?? null,
                    'zip_code' => $erp_ship_address->ShipToZipCode ?? 'N/A',
                    'city' => $erp_ship_address->ShipToCity ?? null,
                    'state' => $erp_ship_address->ShipToState ?? null,
                    'country_code' => $erp_ship_address->ShipToCountryCode ?? null,
                ];

                $address = $local_shipping_addresses->firstWhere('address_code', $erp_ship_address->ShipToNumber);

                if ($address) {
                    $address->fill($erp_address_mapped_data);
                } else {
                    $address = new CustomerAddress($erp_address_mapped_data);
                }

                $address->save();
                $ignore_to_delete[] = $address->id;
            });

            // delete previous extra addresses.
            CustomerAddress::where('customer_id', $this->customer->id)->whereNotIn('id', $ignore_to_delete)->delete();
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error($th);
        }
    }

    /**
     * @throws \Exception
     */
    private function parallelProfileSync()
    {
        $tasks = [
            fn() => $this->syncCustomerInfo(),
            fn() => $this->syncShippingAddress(),
        ];

        $pids = [];

        foreach ($tasks as $task) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new \Exception("Failed to fork process");
            }

            if ($pid === 0) {
                // CHILD PROCESS
                try {
                    $task();
                } catch (\Throwable $e) {
                    \Log::error("Customer Profile Sync Child process error: " . $e->getMessage());
                    \Log::error($e);
                }
                exit;
            }

            // Parent
            $pids[] = $pid;
        }

        // Parent waits for all children
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }

    private function sequentialProfileSync()
    {
        $this->syncCustomerInfo();
        $this->syncShippingAddress();
    }

}
