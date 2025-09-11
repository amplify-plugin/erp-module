<?php

namespace Amplify\ErpApi\Services;

use Amplify\ErpApi\Adapters\DefaultErpAdapter;
use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\CreateQuotationCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Collections\OrderCollection;
use Amplify\ErpApi\Collections\PastItemCollection;
use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Collections\ProductSyncCollection;
use Amplify\ErpApi\Collections\QuotationCollection;
use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\TrackShipmentCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Interfaces\ErpApiInterface;
use Amplify\ErpApi\Traits\BackendShippingCostTrait;
use Amplify\ErpApi\Traits\ErpApiConfigTrait;
use Amplify\ErpApi\Wrappers\Campaign;
use Amplify\ErpApi\Wrappers\Contact;
use Amplify\ErpApi\Wrappers\ContactValidation;
use Amplify\ErpApi\Wrappers\CreateCustomer;
use Amplify\ErpApi\Wrappers\CreateOrUpdateNote;
use Amplify\ErpApi\Wrappers\CreatePayment;
use Amplify\ErpApi\Wrappers\Customer;
use Amplify\ErpApi\Wrappers\CustomerAR;
use Amplify\ErpApi\Wrappers\Document;
use Amplify\ErpApi\Wrappers\Invoice;
use Amplify\ErpApi\Wrappers\Order;
use Amplify\ErpApi\Wrappers\OrderTotal;
use Amplify\ErpApi\Wrappers\Quotation;
use Amplify\ErpApi\Wrappers\ShippingLocation;
use Amplify\ErpApi\Wrappers\ShippingLocationValidation;
use Amplify\System\Backend\Models\CustomerAddress;
use Amplify\System\Backend\Models\CustomerOrder;
use Amplify\System\Backend\Models\CustomerOrderNote;
use Amplify\System\Backend\Models\Product;
use Amplify\System\Backend\Models\ProductAvailability;
use Amplify\System\Backend\Models\ProductSync;
use Amplify\System\Backend\Models\Shipping;
use Amplify\System\Backend\Models\Warehouse;
use Exception;

class DefaultErpService implements ErpApiInterface
{
    use BackendShippingCostTrait;
    use ErpApiConfigTrait;

    public function __construct()
    {
        $this->adapter = new DefaultErpAdapter;

        $this->config = config('amplify.erp.configurations.default');
    }

    /**
     * This API is to create a new cash customer account
     */
    public function createCustomer(array $attributes = []): CreateCustomer
    {
        return $this->adapter->createCustomer();
    }

    /**
     * This API is to get customer entity information from the ERP
     */
    public function getCustomerList(array $filters = []): CustomerCollection
    {
        return $this->adapter->getCustomerList(\Amplify\System\Backend\Models\Customer::all()->toArray());
    }

    /**
     * This API is to get customer entity information from the ERP
     */
    public function getCustomerDetail(array $filters = []): Customer
    {
        $customer_number = $filters['customer_number'] ?? $this->customerId();

        $customer = \Amplify\System\Backend\Models\Customer::where($this->config['customer_id_field'], $customer_number)
            ->with('warehouse')
            ->first();

        $customer = $customer ? $customer->toArray() : [];

        return $this->adapter->getCustomerDetail($customer);

    }

    public function validateCustomerShippingLocation(array $filters = []): ShippingLocationValidation
    {
        $model = new ShippingLocationValidation($filters);

        $model->Name = $filters['shipping_name'] ?? null;
        $model->Address1 = $filters['shipping_address1'] ?? null;
        $model->Address2 = $filters['shipping_address2'] ?? null;
        $model->Address3 = $filters['shipping_address3'] ?? null;
        $model->City = $filters['shipping_city'] ?? null;
        $model->State = $filters['shipping_state'] ?? null;
        $model->ZipCode = $filters['shipping_zip'] ?? null;
        $model->Status = $filters['Status'] ?? null;
        $model->Response = $filters['Response'] ?? 'Success';
        $model->Message = $filters['Message'] ?? null;
        $model->Details = $filters['Details'] ?? null;
        $model->Reference = $filters['Reference'] ?? null;

        return $model;
    }

    /**
     * This API is to create customer ship to locations entity information from the FACTS ERP
     */
    public function createCustomerShippingLocation(array $filters = []): ShippingLocation
    {
        try {
            $address = CustomerAddress::create([
                'customer_id' => $this->customerId(),
                'address_code' => $filters['shipping_number'],
                'address_name' => $filters['shipping_name'],
                'address_1' => $filters['shipping_address1'],
                'address_2' => $filters['shipping_address2'],
                'country_code' => $filters['shipping_country'],
                'state' => $filters['shipping_state'],
                'city' => $filters['shipping_city'],
                'zip_code' => $filters['shipping_zip'],
            ]);

            return $this->adapter->renderSingleCustomerShippingLocation($address->toArray());
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->renderSingleCustomerShippingLocation();
        }
    }

    /**
     * This API is to get customer ship to locations entity
     * information from the ERP
     */
    public function getCustomerShippingLocationList(array $filters = []): ShippingLocationCollection
    {
        try {
            $customer_number = $filters['customer_number'] ?? $this->customerId();
            $locationList = CustomerAddress::whereHas('customer', fn ($q) => $q->where('customer_code', $customer_number))->get();
            $locationList = $locationList ? $locationList->toArray() : [];

            return $this->adapter->getCustomerShippingLocationList($locationList);
        } catch (\Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerShippingLocationList();
        }
    }

    /**
     * This API is to get item details with pricing and
     * availability for the given warehouse location ID
     */
    public function getProductPriceAvailability(array $filters = []): ProductPriceAvailabilityCollection
    {
        try {
            $warehouseCollection = $this->getWarehouses();
            $items = $filters['items'] ?? [];
            $warehouses = [];
            $item_list = [];

            if (isset($filters['warehouse']) && ! empty($filters['warehouse'])) {
                $warehouseStr = $filters['warehouse'];
                $warehouseCollection->each(function ($wh) use (&$warehouses, &$warehouseStr) {
                    if (stripos($warehouseStr, $wh->WarehouseNumber) !== false) {
                        $warehouseStr = str_replace($wh->WarehouseNumber, '', $warehouseStr);
                        $warehouses[] = $wh->WarehouseNumber;
                    }
                });
            } else {
                $warehouseCollection->each(function ($wh) use (&$warehouses) {
                    $warehouses[] = $wh->WarehouseNumber;
                });
            }

            $products = Product::select('id', 'product_code', 'selling_price', 'msrp', 'uom')->whereIn('product_code', array_map(fn ($item) => trim($item['item']), $items))->get();

            foreach ($products ?? [] as $item) {
                foreach ($warehouses as $warehouse) {
                    $selling_price = $this->customerSpecialPrice(floatval(preg_replace('/[^\d\.]/', '', $item->selling_price)), $item->id);
                    $availability = ProductAvailability::firstOrCreate([
                        'item_number' => $item->product_code,
                        'warehouse_id' => $warehouse,
                    ], [
                        'item_number' => $item->product_code,
                        'warehouse_id' => $warehouse,
                        'price' => $selling_price,
                        'list_price' => floatval(preg_replace('/[^\d\.]/', '', $item->msrp)),
                        'standard_price' => floatval(preg_replace('/[^\d\.]/', '', $item->msrp)),
                        'extended_price' => floatval(preg_replace('/[^\d\.]/', '', $item->msrp)),
                        'order_price' => $selling_price,
                        'average_lead_time' => rand(1, 50),
                        'unit_of_measure' => $item->uom ?? 'EA',
                        'quantity_available' => rand(0, 500),
                        'quantity_on_order' => 1,
                    ]);

                    $item_list[] = $availability;

                }
            }

            return $this->adapter->getProductPriceAvailability($item_list);

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getProductPriceAvailability();
        }

    }

    private function customerSpecialPrice($price, $id)
    {
        $discount = $id % 10 + 10;

        return $price - ($price * $discount) / 100;
    }

    public function createQuotation(array $orderInfo = []): CreateQuotationCollection
    {
        try {
            return $this->adapter->createQuotation($orderInfo);

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createQuotation();
        }
    }

    /**
     * This API is to create an order in the ERP
     */
    public function createOrder(array $orderInfo = []): Order
    {
        return $this->adapter->createOrder($orderInfo);
    }

    /**
     * This API is to get details of a order/invoice,
     * or list of orders from a date range
     */
    public function getOrderList(array $filters = []): OrderCollection
    {

        $query = CustomerOrder::where('customer_id', $this->customerId())->with(['orderLines', 'customer' => function ($q) {
            $q->with('addresses');
        }])->latest();

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $this->adapter->getOrderList([$query->get()]);

    }

    /**
     * This API is to get details of an order/invoice,
     * or list of orders from a date range
     */
    public function getOrderDetail(array $orderInfo = []): Order
    {
        $order = collect([]);
        $orderDetail = [];

        if (array_key_exists('order_number', $orderInfo)) {

            $order = CustomerOrder::where('id', $orderInfo['order_number'])
                ->with(['orderLines', 'orderLines.product', 'customer', 'customer.addresses'])
                ->first();

            foreach ($order->orderLines ?? [] as $item) {
                $orderDetail[] = [
                    'LineNumber' => $item->id,
                    'ItemNumber' => $item->product_code,
                    'ItemType' => $item->product->product_type,
                    'ItemDescription1' => $item->product->product_name,
                    'QuantityOrdered' => $item->qty,
                    'UnitOfMeasure' => 'EA',
                    'PricingUM' => 'EA',
                    'ActualSellPrice' => $item->customer_price,
                    'ShipWhse' => 'S1',
                    'TotalLineAmount' => $item->customer_price,

                ];
            }

            $order->OrderDetail = $orderDetail;
        }

        return $this->adapter->getOrderDetail([$order]);
    }

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     */
    public function getQuotationDetail(array $orderInfo = []): Quotation
    {
        return $this->adapter->getQuotationDetail($orderInfo);
    }

    /**
     * This API is to get customer Accounts Receivables information from the ERP
     */
    public function getCustomerARSummary(array $filters = []): CustomerAR
    {
        $total_amount = CustomerOrder::where('customer_id', $this->customerId())->sum('total_amount');
        $last_order = CustomerOrder::where('customer_id', $this->customerId())->latest()->first();
        $customer = [
            'ARSummary' => [
                'CustomerNum' => customer()->customer_code,
                'CustomerName' => customer()->customer_name,
                'Address1' => '',
                'Address2' => '',
                'City' => 'SLC',
                'ZipCode' => '84108',
                'State' => 'UT',
                'AgeDaysPeriod1' => rand(1, 20),
                'AgeDaysPeriod2' => rand(1, 30),
                'AgeDaysPeriod3' => rand(1, 50),
                'AgeDaysPeriod4' => rand(1, 80),
                'AmountDue' => -$total_amount,
                'BillingPeriodAmount' => -$total_amount,
                'DateOfFirstSale' => date('Y-m-d', mt_rand(1262055681, 1262055681)),
                'DateOfLastPayment' => date('Y-m-d', mt_rand(1262055681, 1262055681)),
                'DateOfLastSale' => $last_order ? date('Y-m-d', strtotime($last_order->created_at)) : '',
                'FutureAmount' => '.00',
                'OpenOrderAmount' => '.00',
                'SalesLastYearToDate' => $total_amount,
                'SalesMonthToDate' => $total_amount,
                'SalesYearToDate' => $total_amount,
                'TermsCode' => 'VI',
                'TermsDescription' => 'VISACARD',
                'TradeAgePeriod1Amount' => '0',
                'TradeAgePeriod2Amount' => '0',
                'TradeAgePeriod3Amount' => '0',
                'TradeAgePeriod4Amount' => '0',
                'TradeAmountDue' => '-3,147.90',
                'TradeBillingPeriodAmount' => -$total_amount,
                'AvgDaysToPay1' => rand(1, 30),
                'AvgDaysToPay1Wgt' => rand(1, 30),
                'AvgDaysToPay2' => rand(1, 30),
                'AvgDaysToPay2Wgt' => rand(1, 30),
                'AvgDaysToPay3' => rand(1, 30),
                'AvgDaysToPay3Wgt' => rand(1, 30),
                'AvgDaysToPayDesc1' => 'LAST01PERIODS',
                'AvgDaysToPayDesc2' => 'LAST02PERIODS',
                'AvgDaysToPayDesc3' => 'LAST03PERIODS',
                'CreditCheckType' => '',
                'CreditLimit' => rand(10000, 30000),
                'HighBalance' => rand(10000, 30000),
                'LastPayAmount' => rand(100, 3000),
                'NumInvPastDue' => '0',
                'NumOpenInv' => rand(1, 10),
                'NumPayments1' => rand(1, 10),
                'NumPayments2' => rand(1, 10),
                'NumPayments3' => rand(1, 10),
                'TradeAgePeriod1Text' => '1-30',
                'TradeAgePeriod2Text' => '31-60',
                'TradeAgePeriod3Text' => '61-90',
                'TradeAgePeriod4Text' => 'OVER90',
                'TradeBillingPeriodText' => 'CURRENT',
            ],
        ];

        return $this->adapter->getCustomerARSummary($customer);
    }

    /**
     * This API is to get customer Accounts Receivables
     * Open Invoices data from the ERP
     */
    public function getInvoiceList(array $filters = []): InvoiceCollection
    {
        $invoices = \Amplify\System\Backend\Models\Invoice::where('customer_id', $this->customerId())
            ->when(! empty($filters['invoice_status']), function ($query) use ($filters) {
                $query->where('invoice_status', $filters['invoice_status']);
            })
            ->when(! empty($filters['from_entry_date']), function ($query) use ($filters) {
                $query->where('entry_date', '>=', $filters['from_entry_date']);
            })
            ->when(! empty($filters['to_entry_date']), function ($query) use ($filters) {
                $query->where('entry_date', '<=', $filters['to_entry_date']);
            })
            ->get()
            ->toArray();

        return $this->adapter->getInvoiceList($invoices);
    }

    /**
     * This API is to get customer AR Open Invoice data from the ERP.
     */
    public function getInvoiceDetail(array $filters = []): Invoice
    {
        $invoice = [];
        if (array_key_exists('invoice_number', $filters)) {

            $invoice = \Amplify\System\Backend\Models\Invoice::where('invoice_number', $filters['invoice_number'])->with(['order'])->first();
            $order = $invoice->order;
            $invoice = $invoice ? $invoice->toArray() : [];
            $invoice['order'] = $order;
            if ($invoice['order']) {
                $invoice['has_invoice_detail'] = 'Yes';
                $invoice['order_number'] = $order->id ?? null;
            }

        }

        return $this->adapter->getInvoiceDetail($invoice);
    }

    /**
     * This API is to create a AR payment on the customers account.
     */
    public function createPayment(array $paymentInfo = []): CreatePayment
    {
        return $this->adapter->createPayment([]);
    }

    /**
     * This API is to create an order note.
     */
    public function createOrUpdateNote(array $noteInfo = []): CreateOrUpdateNote
    {
        $type = $noteInfo['type'] ?? 'SOEH';
        $note_number = $noteInfo['noteNumber'] ?? '';
        $order_number = $noteInfo['orderNumber'];
        $note = $noteInfo['note'] ?? '';
        $subject = $noteInfo['subject'] ?? '';

        $customerOrderNote = CustomerOrderNote::updateOrCreate(
            ['id' => $note_number],
            [
                'customer_order_id' => $order_number,
                'subject' => $subject,
                'note' => $note,
                'type' => $type,
            ]
        );

        return $this->adapter->createOrUpdateNote(['id' => $customerOrderNote->id]);
    }

    /**
     * This function will check if the ERP can be enabled
     */
    public function enabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * This function will check if the ERP has Multiple warehouse capabilities
     */
    public function allowMultiWarehouse(): bool
    {
        return $this->config['multiple_warehouse'] ?? false;
    }

    /**
     * This function will return the ERP Carrier code options
     */
    public function getShippingOption(array $data = []): ShippingOptionCollection
    {
        $options = Shipping::enabled()->get()->toArray();

        return $this->adapter->getShippingOption($options);

    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getProductSync(array $filters = []): ProductSyncCollection
    {
        return $this->adapter->getProductSync(ProductSync::all()->toArray());
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getWarehouses(array $filters = []): WarehouseCollection
    {
        return $this->adapter->getWarehouses(Warehouse::all()->toArray());
    }

    /**
     * This API is to get all future campaigns data from the ERP.
     *
     * @throws Exception
     */
    public function getCampaignList(array $filters = []): CampaignCollection
    {
        // @TODO HANDLED USING SYSTEM DATABASE
        try {
            //            $override_date = $filters['override_date'] ?? null;
            //            $promo_type = $filters['promo_type'] ?? 'O';
            //            $promo = $filters['promo'] ?? '';
            //
            //            $payload = [
            //                'content' => [
            //                    'OverrideDate' => $override_date,
            //                    'PromoType' => $promo_type,
            //                    'Promo' => $promo,
            //                ],
            //            ];
            //
            //            $response = $this->post('/getPromotion', $payload);

            return $this->adapter->getCampaignList();
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCampaignList();
        }
    }

    /**
     * This API is to get single campaign details and items
     * info from the ERP.
     *
     * @throws Exception
     */
    public function getCampaignDetail(array $filters = []): Campaign
    {
        // @TODO HANDLED USING SYSTEM DATABASE
        try {
            //            $action = $filters['action'] ?? 'SPECIFIC';
            //            $override_date = $filters['override_date'] ?? null;
            //            $promo_type = $filters['promo_type'] ?? 'O';
            //            $promo = $filters['promo'];
            //
            //            $payload = [
            //                'content' => [
            //                    'Action' => $action,
            //                    'OverrideDate' => $override_date,
            //                    'PromoType' => $promo_type,
            //                    'Promo' => $promo,
            //                ],
            //            ];
            //
            //            $response = $this->post('/getPromotion', $payload);

            return $this->adapter->getCampaignDetail();
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCampaignDetail();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONTACT VALIDATION FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * The API is used to verify customer and contact assignable
     */
    public function contactValidation(array $inputs = []): ContactValidation
    {
        try {
            $contact_email = $inputs['email_address'] ?? null;
            $customer_number = $inputs['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $customer = \Amplify\System\Backend\Models\Customer::whereCustomerCode($customer_number)->first();
            $contact = \Amplify\System\Backend\Models\Contact::whereEmail($contact_email)->first();

            if (! $customer->is_assignable) {
                throw new \Exception('This customer does not have Assignable option enabled.');
            }

            $response['ValidCombination'] = 'Y';
            $response['CustomerNumber'] = $customer_number ?? null;
            $response['ContactNumber'] = $contact->id ?? 'N';
            $response['EmailAddress'] = $contact_email;
            $response['DefaultWarehouse'] = $customer->warehouse?->code ?? null;
            $response['DefaultShipTo'] = $customer->shipto_address_code ?? null;

            return $this->adapter->contactValidation($response);

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->contactValidation();
        }
    }

    /**
     * This API is to get customer required document from ERP
     */
    public function getDocument(array $inputs = []): Document
    {
        try {

            $document = $this->getInvoiceDetail($inputs);

            return $this->adapter->getDocument($document);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getDocument();
        }
    }

    /**
     * This API is to get cost shipping method  of a cart items
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getOrderTotal(array $orderInfo = []): OrderTotal
    {
        try {

            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'CustomerEmail' => $orderInfo['customer_email'] ?? $this->getCustomerDetail()->CustomerEmail,
                    'CarrierCode' => $orderInfo['shipping_method'] ?? 'UPS',
                    'PoNumber' => $order['customer_order_ref'] ?? '',
                    'ShipToNumber' => $orderInfo['ship_to_number'] ?? '',
                    'ShipToAddress1' => $orderInfo['ship_to_address1'] ?? '',
                    'ShipToAddress2' => $orderInfo['ship_to_address2'] ?? '',
                    'ShipToAddress3' => $orderInfo['ship_to_address3'] ?? '',
                    'ShipToCity' => $orderInfo['ship_to_city'] ?? '',
                    'ShipToCntryCode' => $orderInfo['ship_to_country_code'] ?? '',
                    'ShipToState' => $orderInfo['ship_to_state'] ?? '',
                    'ShipToZipCode' => $orderInfo['ship_to_zip_code'] ?? '',
                    'OrderType' => $orderInfo['order_type'] ?? 'T',
                    'ReturnType' => $orderInfo['return_type'] ?? 'D',
                    'Items' => $orderInfo['items'] ?? [],
                ],
            ];

            if (config('amplify.erp.use_amplify_shipping')) {
                $response = $this->getOrderTotalUsingBackend($payload);
            } else {

                $url = match (config('amplify.client_code')) {
                    'ACT', 'MW' => 'createOrder',
                    default => 'getOrderTotal',
                };
                $response = $this->post("/{$url}", $payload);
            }

            return $this->adapter->getOrderTotal($response);

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getOrderTotal();
        }
    }

    /**
     * Fetch shipment tracking URL
     *
     * @throws Exception
     */
    public function getTrackShipment(array $inputs = []): TrackShipmentCollection
    {
        try {

            $customer_number = $inputs['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $invoice_number = $inputs['invoice_number'] ?? null;

            $query = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'InvoiceNumber' => $invoice_number,
                ],
            ];

            $url = 'getTrackShipmentInfo';
            $response = $this->post("/{$url}", $query);

            return $this->adapter->getTrackShipment($response);

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getTrackShipment();
        }
    }

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     */
    public function getQuotationList(array $filters = []): QuotationCollection
    {
        return $this->adapter->getQuotationList();
    }

    /**
     * This API is to get customer past sales items from the FACTS ERP
     */
    public function getPastItemList(array $filters = []): PastItemCollection
    {
        return $this->adapter->getPastItemList();
    }

    /*
    |--------------------------------------------------------------------------
    | CONTACT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a new cash customer account
     *
     * @done untested
     *
     * @throws Exception
     *
     * @since 2024.12.8354871
     */
    public function createUpdateContact(array $attributes = []): Contact
    {
        return new Contact($attributes);
    }

    /**
     * This API is to get customer entity information from the CSD ERP
     *
     * @throws Exception
     *
     * @done
     *
     * @todo Adapter mapping pending
     *
     * @since 2024.12.8354871
     */
    public function getContactList(array $filters = []): ContactCollection
    {
        return new ContactCollection;
    }

    /**
     * This API is to get customer entity information from the CSD ERP
     *
     * @done
     *
     * @throws Exception
     *
     * @since 2024.12.8354871
     */
    public function getContactDetail(array $filters = []): Contact
    {
        return new Contact($filters);
    }
}
