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
     */
    public function handle()
    {
        if (config('amplify.basic.client_code') != 'ACP') {
            $this->syncCustomerInfo();
            $this->syncShippingAddress();
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

        $customer_erp_data = ErpApi::getCustomerDetail(['customer_number' => $this->customer->customer_code]);
        $warehouse = Warehouse::where('code', $customer_erp_data['DefaultWarehouse'])->first();

        $customer_modified_data = [
            'ar_number' => $customer_erp_data['ArCustomerNumber'] ?? null,
            'class' => $customer_erp_data['CustomerClass'] ?? null,
            'shipto_address_code' => $customer_erp_data['DefaultShipTo'] ?? null,
            'suspend_code' => $customer_erp_data['SuspendCode'] ?? null,
            'warehouse_id' => ! empty($warehouse) ? $warehouse->id : null,
            'carrier_code' => $customer_erp_data['CarrierCode'] ?? null,
            'business_contact' => $customer_erp_data['SalesPersonEmail'] ?? null,
            'customer_po_required' => $customer_erp_data['PoRequired'] == 'Y',
            'allow_backorder' => $customer_erp_data['BackorderCode'] == 'Y',
            'credit_card_only' => $customer_erp_data['CreditCardOnly'] == 'Y',
            'free_shipment_amount' => empty($customer_erp_data['FreightOptionAmount']) ? null : filter_var($customer_erp_data['FreightOptionAmount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'own_truck_ship_charge' => empty($customer_erp_data['OTShipPrice']) ? null : filter_var($customer_erp_data['OTShipPrice'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
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
            $erp_shipping_addresses = ErpApi::getCustomerShippingLocationList(['customer_number' => $this->customer->customer_code]);
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
}
