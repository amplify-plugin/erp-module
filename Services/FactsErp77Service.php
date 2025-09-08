<?php

namespace Amplify\ErpApi\Services;

use Amplify\ErpApi\Adapters\FactsErp77Adapter;
use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\CreateQuotationCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Collections\OrderCollection;
use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Collections\ProductSyncCollection;
use Amplify\ErpApi\Collections\QuotationCollection;
use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\ErpApiService;
use Amplify\ErpApi\Exceptions\FactsErpException;
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
use Amplify\ErpApi\Wrappers\TrackShipment;
use Amplify\System\Backend\Models\Shipping;
use Exception;
use Illuminate\Support\Facades\Http;

/**
 * @property array $config
 */
class FactsErp77Service implements ErpApiInterface
{
    use BackendShippingCostTrait;
    use ErpApiConfigTrait;

    private array $commonHeaders;

    public function __construct()
    {
        $this->adapter = new FactsErp77Adapter;

        $this->config = config('amplify.erp.configurations.facts-erp-77');

        $this->commonHeaders = [
            'Content-Type' => 'application/json',
            'Consumerkey' => $this->config['username'],
            'Password' => $this->config['password'],
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITY FUNCTIONS
    |--------------------------------------------------------------------------
    */

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
     * @throws FactsErpException
     */
    private function post(string $url, array $payload = []): array
    {
        if (! $this->enabled()) {
            throw new FactsErpException('Facts ERP  7.7 Service is disabled.', 500);
        }

        $response = Http::timeout(10)
            ->withoutVerifying()
            ->withHeaders($this->commonHeaders)
            ->post(($this->config['url'].$url), $payload);

        // Item Master API Response RAW Logging
        if ($url == '/get_item_master.php') {
            $this->logProductSyncResponse($response->body());
        }

        return $this->validate($response->body());
    }

    /**
     * This function will check if the ERP can be enabled
     */
    public function enabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Validate the API call response
     *
     * @param  mixed  $response
     *
     * @throws FactsErpException|Exception
     */
    private function validate(string $response): array
    {
        try {
            if (strlen($response) == 0) {
                throw new FactsErpException("Empty Response Received ({$response})", 500);
            }

            $response = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new FactsErpException('Invalid JSON Error ('.json_last_error_msg().')', 500);
            }

            if (isset($response['error'])) {
                $firstError = array_shift($response['error']);
                throw new FactsErpException(
                    'Validation Error ('.($firstError['message'] ?? '').')',
                    ($firstError['code'] ?? 422)
                );
            }

            return is_array($response)
                ? $response
                : (array) $response;
        } catch (FactsErpException $exception) {
            $this->exceptionHandler($exception);

            return [];
        }
    }

    /**
     * @throws FactsErpException
     */
    private function get(string $url, $query = null): array
    {
        if (! $this->enabled()) {
            throw new FactsErpException('Facts ERP Service is disabled.', 500);
        }

        $response = Http::timeout(10)
            ->withoutVerifying()
            ->withHeaders($this->commonHeaders)
            ->baseUrl($this->config['url'])
            ->get($url, $query);

        return $this->validate($response->body());
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a new cash customer account
     */
    public function createCustomer(array $attributes = []): CreateCustomer
    {
        throw new FactsErpException('Under Construction');
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $PhoneNumber = $attributes['phone_number'] ?? null;
            $FirstName = $attributes['first_name'] ?? null;
            $LastName = $attributes['last_name'] ?? null;
            $City = $attributes['city'] ?? null;
            $Address1 = $attributes['address_1'] ?? null;
            $Address2 = $attributes['address_2'] ?? null;
            $State = $attributes['state'] ?? null;
            $ZipCode = $attributes['zip_code'] ?? null;
            $Branch = $attributes['branch'] ?? null;
            $Industry = $attributes['industry'] ?? null;
            $BusinessName = $attributes['business_name'] ?? null;
            $ElectronicCommunication = $attributes['electronic_communication'] ?? null;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'PhoneNumber' => $PhoneNumber,
                    'FirstName' => $FirstName,
                    'LastName' => $LastName,
                    'City' => $City,
                    'Address1' => $Address1,
                    'Address2' => $Address2,
                    'State' => $State,
                    'ZipCode' => $ZipCode,
                    'Branch' => $Branch,
                    'Industry' => $Industry,
                    'BusinessName' => $BusinessName,
                    'ElectronicCommunication' => $ElectronicCommunication,
                ],
            ];

            $response = $this->post('/createCashCustomer', $payload);

            return $this->adapter->createCustomer($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createCustomer();
        }
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     *
     *
     * @throws Exception
     */
    public function getCustomerList(array $filters = []): CustomerCollection
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $customer_start = $filters['customer_start'] ?? null;
            $customer_end = $filters['customer_end'] ?? null;

            $query = [
                'EmailAddress' => $EmailAddress,
                'CustomerStart' => $customer_start,
                'CustomerEnd' => $customer_end,
            ];

            $response = $this->post('/get_customers.php', $query);

            return $this->adapter->getCustomerList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerList();
        }
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     */
    public function getCustomerDetail(array $filters = []): Customer
    {
        try {
            $customer_email = $filters['customer_email'] ?? $this->customerEmail();
            $customer_number = $filters['customer_number'] ?? $this->customerId();

            if ($customer_number == null) {
                throw new FactsErpException('Customer Code is missing.');
            }

            $query = [
                'content' => [
                    'EmailAddress' => $customer_email,
                    'CustomerNumber' => $customer_number,
                ],
            ];

            $response = $this->post('/get_customers.php', $query);

            // $response = json_decode('{
            //     "Customers": [
            //         {
            //             "CustomerNumber": "GAVINTEST",
            //             "ArCustomerNumber": "GAVINTEST",
            //             "CustomerName": "Gavin Barrett",
            //             "CustomerAddress1": "2000 South 1200 East",
            //             "CustomerAddress2": "",
            //             "CustomerAddress3": "",
            //             "CustomerCity": "SALT LAKE CITY",
            //             "CustomerState": "UT",
            //             "CustomerZipCode": "84121",
            //             "CustomerEmail": "gavin.barrett@sequoiagroup.com",
            //             "CustomerPhone": "801-703-4451",
            //             "CustomerContact": "Gavin Barrett",
            //             "DefaultShipTo": "SAME",
            //             "DefaultWarehouse": "01",
            //             "CarrierCode": "U",
            //             "PriceList": "1",
            //             "FreeFreight": "N",
            //             "FreeFreightMinimum": "0",
            //             "BackorderCode": "Y",
            //             "CustomerClass": "1",
            //             "TermsCode": "4",
            //             "SuspendCode": "N",
            //             "AllowArPayments": "Yes",
            //             "CreditCardOnly": "N",
            //             "PoRequired": "Y",
            //             "SalesPersonCode": "2",
            //             "SalesPersonName": "House Accounts",
            //             "SalesPersonEmail": ""
            //         }
            //     ]
            // }', true);

            return $this->adapter->getCustomerDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerDetail();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to validate customer ship to locations entity information from the FACTS ERP
     */
    public function validateCustomerShippingLocation(array $filters = []): ShippingLocationValidation
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();

            $query = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'Name' => $filters['shipping_name'] ?? null,
                    'Address1' => $filters['shipping_address1'],
                    'Address2' => $filters['shipping_address2'] ?? null,
                    'City' => $filters['shipping_city'],
                    'State' => $filters['shipping_state'],
                    'ZipCode' => $filters['shipping_zip'],
                    'Options' => $filters['shipping_options'] ?? null,
                ],
            ];
            $response = $this->post('/get_address_verification.php', $query);

            return $this->adapter->validateCustomerShippingLocation($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->validateCustomerShippingLocation();
        }
    }

    /**
     * This API is to create customer ship to locations entity information from the FACTS ERP
     */
    public function createCustomerShippingLocation(array $filters = []): ShippingLocation
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $contact_id = $filters['contact_id'] ?? null;

            $query = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'Customer' => $customer_number,
                    'ContactId' => $contact_id,
                    'ShipToNumber' => $filters['shipping_number'],
                    'ShipToName' => $filters['shipping_name'],
                    'ShipToAddress1' => $filters['shipping_address1'],
                    'ShipToAddress2' => $filters['shipping_address2'],
                    'ShipToContact1' => $filters['shipping_contact1'],
                    'ShipToContact2' => $filters['shipping_contact2'],
                    'ShipToPhone1' => $filters['shipping_phone1'],
                    'ShipToPhone2' => $filters['shipping_phone2'],
                    'ShipToEmail1' => $filters['shipping_email1'],
                    'ShipToEmail2' => $filters['shipping_email2'],
                    'ShipToCity' => $filters['shipping_city'],
                    'ShipToStateCode' => $filters['shipping_state'],
                    'ShipToZipCode' => $filters['shipping_zip'],
                ],
            ];
            $response = $this->post('/create_shipto.php', $query);

            return $this->adapter->createCustomerShippingLocation($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createCustomerShippingLocation();
        }
    }

    /**
     * This API is to get customer ship to locations entity information from the FACTS ERP
     */
    public function getCustomerShippingLocationList(array $filters = []): ShippingLocationCollection
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $contact_id = $filters['contact_id'] ?? null;
            $login_id = $filters['login_id'] ?? null;

            $query = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'Customer' => $customer_number,
                    'ContactId' => $contact_id,
                    'LoginID' => $login_id,
                ],
            ];
            $response = $this->post('/get_customer_locations.php', $query);

            return $this->adapter->getCustomerShippingLocationList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerShippingLocationList();
        }
    }

    /**
     * This API is to get item details with pricing and availability for the given warehouse location ID
     */
    public function getProductPriceAvailability(array $filters = []): ProductPriceAvailabilityCollection
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $items = $filters['items'] ?? [];
            $warehouse = $filters['warehouse'] ?? null;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $contact_id = $filters['contact_id'] ?? null;
            $show_detail = $filters['show_detail'] ?? 'Y';
            $login_id = $filters['login_id'] ?? null;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'CustomerNumber' => $customer_number,
                    'ContactId' => $contact_id,
                    'ShowDetail' => $show_detail,
                    'LoginID' => $login_id,
                    'Version' => '2',
                    'WarehouseList' => $warehouse,
                    'Items' => $items,
                ],
            ];

            $response = $this->post('/get_price_and_availability.php', $payload);

            return $this->adapter->getProductPriceAvailability($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getProductPriceAvailability();
        }
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     *
     * @throw Exception
     */
    public function getProductSync(array $filters = []): ProductSyncCollection
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $itemStart = $filters['item_start'] ?? '';
            $itemEnd = $filters['item_end'] ?? '';
            $itemClass = $filters['item_class'] ?? '';
            $updatesOnly = $filters['updates_only'] ?? 'N';
            $processUpdates = $filters['process_updates'] ?? 'N';
            $maxRecords = $filters['limit'] ?? '';
            $restartPoint = $filters['restart_point'] ?? '';

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'ItemStart' => $itemStart,
                    'ItemEnd' => $itemEnd,
                    'ItemClass' => $itemClass,
                    'UpdatesOnly' => $updatesOnly,
                    'ProcessUpdates' => $processUpdates,
                    'MaxRecords' => $maxRecords,
                    'RestartPoint' => $restartPoint,
                ],
            ];

            $response = $this->post('/get_item_master.php', $payload);

            return $this->adapter->getProductSync($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getProductSync();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     *
     * ** Note this function does not make andy api call **
     *
     * @throws FactsErpException|Exception
     */
    public function getWarehouses(array $filters = []): WarehouseCollection
    {

        try {
            $warehouseCollection = \Amplify\System\Backend\Models\Warehouse::where($filters)->get();

            $jsonWarehouses = json_encode($warehouseCollection);

            $response = $this->validate($jsonWarehouses);

            return $this->adapter->getWarehouses($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getWarehouses();
        }
    }

    /**
     * This API is to create an order in the FACTS ERP
     */
    public function createOrder(array $orderInfo = []): Order
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $order = $orderInfo['order'] ?? [];
            $items = $orderInfo['items'] ?? [];
            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'CustomerEmail' => $order['customer_email'] ?? $this->getCustomerDetail()->CustomerEmail,
                    'CustomerNumber' => $customer_number,
                    'ContactId' => $order['contact_id'] ?? '',
                    'PhoneNumber' => $order['phone_number'] ?? '',
                    'CarrierCode' => $order['shipping_method'] ?? 'UPS',
                    'PaymentType' => $order['payment_type'] ?? 'Standard',
                    'PoNumber' => $order['customer_order_ref'] ?? uniqid(),
                    'RequestedShipDate' => $order['requested_ship_date'] ?? '',
                    'ShipToNumber' => ! empty($order['ship_to_number']) ? $order['ship_to_number'] : 'TEMP',
                    'ShipToAddress1' => $order['ship_to_address1'] ?? '',
                    'ShipToAddress2' => $order['ship_to_address2'] ?? '',
                    'ShipToAddress3' => $order['ship_to_address3'] ?? '',
                    'ShipToCity' => $order['ship_to_city'] ?? '',
                    'ShipToCntryCode' => $order['ship_to_country_code'] ?? '',
                    'ShipToState' => $order['ship_to_state'] ?? '',
                    'ShipToZipCode' => $order['ship_to_zip_code'] ?? '',
                    'OrderType' => $order['order_type'] ?? 'O',
                    'OrderNote' => $order['order_note'] ?? '',
                    'ReturnType' => $order['return_type'] ?? 'D',
                    'CardToken' => $order['card_token'] ?? '',
                    'WarehouseID' => $order['warehouse_id'] ?? '',
                    'Items' => $items,
                ],
            ];

            $response = $this->post('/create_order_load.php', $payload);

            return $this->adapter->createOrder($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createOrder();
        }
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderList(array $filters = []): OrderCollection
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $lookupType = $filters['lookup_type'] ?? ErpApiService::LOOKUP_OPEN_ORDER; // LookupType  (D/E/H/O)
            $listDetail = $filters['list_detail'] ?? null;
            $fromEntryDate = $filters['start_date'] ?? null;
            $toEntryDate = $filters['end_date'] ?? null;
            $maxRecords = $filters['limit'] ?? null;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $contact_id = $filters['contact_id'] ?? null;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'CustomerNumber' => $customer_number,
                    'ContactId' => $contact_id,
                    'LookupType' => $lookupType,
                    'ListDetail' => $listDetail,
                    'FromEntryDate' => $fromEntryDate,
                    'ToEntryDate' => $toEntryDate,
                    'MaxRecords' => $maxRecords,
                ],
            ];

            $response = $this->post('/get_order.php', $payload);

            return $this->adapter->getOrderList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getOrderList();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | QUOTATION FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderDetail(array $orderInfo = []): Order
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $invoice_number = $orderInfo['invoice_number'] ?? null;
            $order_number = $orderInfo['order_number'] ?? null;
            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'CustomerNumber' => $customer_number,
                    'LookupType' => ErpApiService::LOOKUP_HISTORICAL_ORDER,
                    'InvoiceNumber' => $invoice_number,
                    'OrderNumber' => $order_number,
                ],
            ];

            $response = $this->post('/get_order.php', $payload);

            return $this->adapter->getOrderDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getOrderDetail();
        }
    }

    /**
     * This API is to create a quotation in the FACTS ERP
     */
    public function createQuotation(array $orderInfo = []): CreateQuotationCollection
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $order = $orderInfo['order'] ?? [];
            $items = $orderInfo['items'] ?? [];
            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'CustomerEmail' => $EmailAddress,
                    'CustomerNumber' => $customer_number,
                    'ShipToNumber' => $order['ship_to_number'] ?? '',
                    'OrderType' => $order['order_type'] ?? 'T',
                    'ReturnType' => $order['return_type'] ?? 'D',
                    'Items' => $items,
                ],
            ];
            $response = $this->post('/get_order_total.php', $payload);

            return $this->adapter->createQuotation($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createQuotation();
        }
    }

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     */
    public function getQuotationList(array $filters = []): QuotationCollection
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $quote_number = $filters['quote_number'] ?? null;
            $fromEntryDate = $filters['start_date'] ?? null;
            $toEntryDate = $filters['end_date'] ?? null;
            $maxRecords = $filters['limit'] ?? null;
            $restartPoint = $filters['restartPoint'] ?? null;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $contact_id = $filters['contact_id'] ?? null;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'CustomerNumber' => $customer_number,
                    'ContactId' => $contact_id,
                    'QuoteNumber' => $quote_number,
                    'FromEntryDate' => $fromEntryDate,
                    'ToEntryDate' => $toEntryDate,
                    'RestartPoint' => $restartPoint,
                    'MaxRecords' => $maxRecords,
                ],
            ];

            $response = $this->post('/get_quote.php', $payload);

            return $this->adapter->getQuotationList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getQuotationList();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INVOICE FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     */
    public function getQuotationDetail(array $orderInfo = []): Quotation
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $quote_number = $orderInfo['quote_number'] ?? null;
            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'CustomerNumber' => $customer_number,
                    'QuoteNumber' => $quote_number,
                ],
            ];

            $response = $this->post('/get_quote.php', $payload);

            return $this->adapter->getQuotationDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getQuotationDetail();
        }
    }

    /**
     * This API is to get customer Accounts Receivables information from the FACTS ERP
     */
    public function getCustomerARSummary(array $filters = []): CustomerAR
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'CustomerNum' => $customer_number,
                ],
            ];

            $response = $this->post('/get_customer_ar.php', $payload);

            return $this->adapter->getCustomerARSummary($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerARSummary();
        }
    }

    /**
     * This API is to get customer Accounts Receivables Open Invoices data from the FACTS ERP
     */
    public function getInvoiceList(array $filters = []): InvoiceCollection
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $contact_id = $filters['contact_id'] ?? null;
            $fromEntryDate = $filters['start_date'] ?? null;
            $toEntryDate = $filters['end_date'] ?? null;
            $maxRecords = $filters['limit'] ?? null;
            $restartPoint = $filters['restartPoint'] ?? '';

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'Customer' => $customer_number,
                    'ContactId' => $contact_id,
                    'FromEntryDate' => $fromEntryDate,
                    'ToEntryDate' => $toEntryDate,
                    'MaxRecords' => $maxRecords,
                    'RestartPoint' => $restartPoint,
                ],
            ];

            $response = $this->post('/get_ar_invoice.php', $payload);

            return $this->adapter->getInvoiceList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getInvoiceList();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get customer AR Open Invoice data from the FACTS ERP.
     *
     * @return Invoice|null
     *
     * @throws Exception
     */
    public function getInvoiceDetail(array $filters = []): Invoice
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $invoice_number = $filters['invoice_number'] ?? null;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'Customer' => $customer_number,
                    'InvoiceNumber' => $invoice_number,
                ],
            ];

            $response = $this->post('/get_ar_invoice.php', $payload);

            return $this->adapter->getInvoiceDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getInvoiceDetail();
        }
    }

    /**
     * This API is to create an AR payment on the customers account.
     */
    public function createPayment(array $paymentInfo = []): CreatePayment
    {
        throw new FactsErpException('Under Construction');
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $invoices = $paymentInfo['invoices'] ?? [];
            $payment = $paymentInfo['paymentInfo'] ?? [];
            $type = $paymentInfo['type'] ?? [];
            $customer_number = $paymentInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'CustomerNumber' => $customer_number,
                    'Type' => $type,
                    'Invoices' => $invoices,
                ] + $payment,
            ];

            $response = $this->post('/arPayment', $payload);

            return $this->adapter->createPayment($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createPayment();
        }
    }

    /**
     * This API is to create an order note.
     */
    public function createOrUpdateNote(array $noteInfo = []): CreateOrUpdateNote
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $type = $noteInfo['type'] ?? 'SOEH';
            $note_number = $noteInfo['noteNumber'] ?? '';
            $order_number = $noteInfo['orderNumber'];
            $note = $noteInfo['note'] ?? '';

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'Type' => $type,
                    'NoteNum' => $note_number,
                    'OrderNumber' => $order_number,
                    'Note' => $note,
                ],
            ];

            $response = $this->post('/UpdateNote', $payload);

            return $this->adapter->createOrUpdateNote($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createOrUpdateNote();
        }
    }

    /**
     * This API is to get all future campaigns data from the ERP.
     *
     * @throws Exception
     */
    public function getCampaignList(array $filters = []): CampaignCollection
    {
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $override_date = $filters['override_date'] ?? null;
            $promo_type = $filters['promo_type'] ?? 'O';
            $promo = $filters['promo'] ?? '';

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'OverrideDate' => $override_date,
                    'PromoType' => $promo_type,
                    'Promo' => $promo,
                ],
            ];

            $response = $this->post('/get_promotion.php', $payload);

            return $this->adapter->getCampaignList($response);
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
        try {
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $action = $filters['action'] ?? 'SPECIFIC';
            $override_date = $filters['override_date'] ?? null;
            $promo_type = $filters['promo_type'] ?? 'O';
            $promo = $filters['promo'];

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'Action' => $action,
                    'OverrideDate' => $override_date,
                    'PromoType' => $promo_type,
                    'Promo' => $promo,
                ],
            ];

            $response = $this->post('/get_promotion.php', $payload);

            return $this->adapter->getCampaignDetail($response);
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

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'EmailAddress' => $contact_email,
                ],
            ];

            $response = $this->post('/get_contact_validation.php', $payload);

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

                $url = match (config('amplify.basic.client_code')) {
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
    public function getTrackShipment(array $inputs = []): TrackShipment
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
