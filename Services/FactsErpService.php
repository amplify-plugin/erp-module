<?php

namespace Amplify\ErpApi\Services;

use Amplify\ErpApi\Adapters\FactsErpAdapter;
use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\CreateQuotationCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\CylinderCollection;
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
use Amplify\ErpApi\Wrappers\ShippingLocationValidation;
use Amplify\System\Backend\Models\Shipping;
use Exception;
use Illuminate\Support\Facades\Http;

/**
 * @property array $config
 */
class FactsErpService implements ErpApiInterface
{
    use BackendShippingCostTrait;
    use ErpApiConfigTrait;

    private array $commonHeaders;

    public function __construct()
    {
        $this->adapter = new FactsErpAdapter;

        $this->config = config('amplify.erp.configurations.facts-erp');

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
     * @throws FactsErpException
     */
    private function post(string $url, array $payload = []): array
    {
        $response = Http::timeout(10)
            ->withoutVerifying()
            ->withHeaders($this->commonHeaders)
            ->post(($this->config['url'] . $url), $payload);

        // Item Master API Response RAW Logging
        if ($url == '/itemMaster') {
            $this->logProductSyncResponse($response->body());
        }

        return $this->validate($response->body());
    }

    /**
     * @throws FactsErpException
     */
    private function get(string $url, $query = null): array
    {

        $response = Http::timeout(10)
            ->withoutVerifying()
            ->withHeaders($this->commonHeaders)
            ->baseUrl($this->config['url'])
            ->get($url, $query);

        return $this->validate($response->body());
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
        $response = str_replace(["\'", '",}', '",]'], ["'", '"}', '"]'], trim(preg_replace('/\s+/', ' ', $response)));

        // Use a regular expression to remove the extra comma
        $response = preg_replace('/({|\\[)\\s*,/', '$1', $response);

        try {
            if (strlen($response) == 0) {
                throw new FactsErpException("Empty Response Received ({$response})", 500);
            }

            $response = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new FactsErpException('Invalid JSON Error (' . json_last_error_msg() . ')', 500);
            }

            if (isset($response['error'])) {
                $firstError = array_shift($response['error']);
                throw new FactsErpException(
                    'Validation Failed Message (' . ($firstError['message'] ?? '') . ')',
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

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a new cash customer account
     *
     * @since 1.5.0
     */
    public function createCustomer(array $attributes = []): CreateCustomer|Customer
    {
        try {
            $TemplateCustomerNumber = $attributes['template_customer_number'] ?? 'ONLINE';
            $EmailAddress = $attributes['email_address'] ?? null;
            $PhoneNumber = $attributes['phone_number'] ?? null;
            $CustomerName = $attributes['customer_name'] ?? null;
            $Contact = $attributes['contact'] ?? null;
            $Address1 = $attributes['address_1'] ?? null;
            $Address2 = $attributes['address_2'] ?? null;
            $Address3 = $attributes['address_3'] ?? null;
            $City = $attributes['city'] ?? null;
            $State = $attributes['state'] ?? null;
            $ZipCode = $attributes['zip_code'] ?? null;
            $Branch = $attributes['branch'] ?? null;
            $CustomerIndustry = $attributes['customer_industry'] ?? null;

            $payload = [
                'content' => [
                    'TemplateCustomerNumber' => $TemplateCustomerNumber,
                    'CustomerEmail' => $EmailAddress,
                    'CustomerPhone' => $PhoneNumber,
                    'CustomerName' => $CustomerName,
                    'CustomerContact' => $Contact,
                    'CustomerAddress1' => $Address1,
                    'CustomerAddress2' => $Address2,
                    'CustomerAddress3' => $Address3,
                    'CustomerCity' => $City,
                    'CustomerState' => $State,
                    'CustomerZipCode' => $ZipCode,
                    'LocalBranch' => $Branch,
                    'CustomerIndustry' => $CustomerIndustry,
                ],
            ];

            $response = $this->post('/createCustomer', $payload);

            return $this->adapter->createCustomer($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createCustomer();
        }
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     *
     * @throws Exception
     *
     * @since 1.0.0
     */
    public function getCustomerList(array $filters = []): CustomerCollection
    {
        try {
            $customer_start = $filters['customer_start'] ?? null;

            $customer_end = $filters['customer_end'] ?? null;

            $query = [
                'CustomerStart' => $customer_start,
                'CustomerEnd' => $customer_end,
            ];

            if (! empty($filters['GetShipVias'])) {
                $query['GetShipVias'] = $filters['GetShipVias'];
            }

            $response = $this->get('/customers', $query);

            return $this->adapter->getCustomerList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerList();
        }
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     *
     * @since 1.0.0
     */
    public function getCustomerDetail(array $filters = []): Customer
    {
        try {
            $customer_number = $filters['customer_number'] ?? $this->customerId();
            if ($customer_number == null) {
                throw new FactsErpException('Customer Code is missing.');
            }

            $response = $this->get("/customers/{$customer_number}");

            return $this->adapter->getCustomerDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerDetail();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SHIPPING FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This function will return the ERP Carrier code options
     *
     * @since 1.0.0
     */
    public function getShippingOption(array $data = []): ShippingOptionCollection
    {
        $items = $data['items'] ?? [];
        $customer = $this->getCustomerDetail();

        $payload = [
            'content' => [
                'CustomerNumber' => $this->customerId($data),
                'CustomerEmail' => $customer->CustomerEmail,
                'CarrierCode' => $data['customer_carrier_code'] ?? $customer->CarrierCode,
                'PoNumber' => $data['customer_po_number'] ?? '',
                'ShipToAddress1' => $data['customer_address_one'] ?? $customer->CustomerAddress1,
                'ShipToAddress2' => $data['customer_address_two'] ?? $customer->CustomerAddress2,
                'ShipToAddress3' => $data['customer_address_three'] ?? $customer->CustomerAddress3,
                'ShipToCity' => $data['customer_city'] ?? $customer->CustomerCity,
                'ShipToState' => $data['customer_state'] ?? $customer->CustomerState,
                'ShipToZipCode' => $data['customer_zipcode'] ?? $customer->CustomerZipCode,
                'OrderType' => 'T',
                'ReturnType' => 'D',
                // "WarehouseID" => $customerDetails->DefaultWarehouse,
                'Items' => $items,
            ],
        ];

        if (in_array(config('amplify.client_code'), ['ACT', 'RHS'])) {
            try {
                $attributes = match (config('amplify.client_code')) {
                    'ACT' => $this->post('/createOrder', $payload),
                    'RHS' => $this->post('/getOrderTotal', $payload),
                };

                return $this->adapter->getShippingOption($attributes);
            } catch (\Exception $e) {
                $this->exceptionHandler($e);
            }
        }

        $options = Shipping::enabled()->get()->toArray();

        return $this->adapter->getShippingOption($options);
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
     * This API is to get customer ship to locations entity information from the FACTS ERP
     *
     * @since 1.0.0
     */
    public function getCustomerShippingLocationList(array $filters = []): ShippingLocationCollection
    {
        try {
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $response = $this->get("/locations/{$customer_number}");

            return $this->adapter->getCustomerShippingLocationList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerShippingLocationList();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get item details with pricing and availability for the given warehouse location ID
     *
     * @since 1.0.0
     */
    public function getProductPriceAvailability(array $filters = []): ProductPriceAvailabilityCollection
    {
        try {
            $items = $filters['items'] ?? [];

            $warehouse = $filters['warehouse'] ?? null;

            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'Customer' => $customer_number,
                    'WarehouseList' => $warehouse,
                    'Items' => $items,
                ],
            ];

            $response = $this->post('/priceandavailability', $payload);

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
     * @since 1.0.0
     *
     * @throw Exception
     */
    public function getProductSync(array $filters = []): ProductSyncCollection
    {
        try {
            $itemStart = $filters['item_start'] ?? '';
            $itemEnd = $filters['item_end'] ?? '';
            $updatesOnly = $filters['updates_only'] ?? 'N';
            $processUpdates = $filters['process_updates'] ?? 'N';
            $maxRecords = $filters['limit'] ?? '';
            $restartPoint = ($filters['restart_point'] ?? isset($filters['restartPoint'])) ? $filters['restartPoint'] : '';

            $payload = [
                'content' => [
                    'ItemStart' => $itemStart,
                    'ItemEnd' => $itemEnd,
                    'UpdatesOnly' => $updatesOnly,
                    'ProcessUpdates' => $processUpdates,
                    'MaxRecords' => $maxRecords,
                    'RestartPoint' => $restartPoint,
                ],
            ];

            $response = $this->post('/itemMaster', $payload);

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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
     */
    public function createOrder(array $orderInfo = []): Order
    {
        try {
            $order = $orderInfo['order'] ?? [];
            $items = $orderInfo['items'] ?? [];
            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'CustomerEmail' => $order['customer_email'] ?? $this->getCustomerDetail()->CustomerEmail,
                    'CarrierCode' => $order['shipping_method'] ?? 'UPS',
                    'PaymentType' => $order['payment_type'] ?? 'Standard',
                    'PoNumber' => $order['customer_order_ref'] ?? '',
                    'RequestedShipDate' => $order['requested_ship_date'] ?? '',
                    'ShipToName' => $order['ship_to_name'] ?? '',
                    'ShipToNumber' => $order['ship_to_number'] ?? '',
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

            if (config('amplify.erp.use_amplify_shipping')) {
                $payload['content']['FreightAmount'] = $order['freight_amount'] ?? 0;
            }

            $response = $this->post('/createOrder', $payload);

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
            $lookupType = $filters['lookup_type'] ?? ErpApiService::LOOKUP_OPEN_ORDER; // LookupType  (D/E/H/O)
            $fromEntryDate = $filters['start_date'] ?? null;
            $EmailAddress = $filters['email_address'] ?? $this->customerEmail();
            $toEntryDate = $filters['end_date'] ?? null;
            $maxRecords = $filters['limit'] ?? null;
            $restartPoint = $filters['restartPoint'] ?? null;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $listDetail = $filters['list_detail'] ?? 'N';
            $contact_id = $filters['contact_id'] ?? null;

            $payload = [
                'content' => [
                    'EmailAddress' => $EmailAddress,
                    'CustomerNumber' => $customer_number,
                    'ContactId' => $contact_id,
                    'LookupType' => $lookupType,
                    'FromEntryDate' => $fromEntryDate,
                    'ToEntryDate' => $toEntryDate,
                    'MaxRecords' => $maxRecords,
                    'RestartPoint' => $restartPoint,
                    'ListDetail' => $listDetail,
                ],
            ];

            $url = in_array(config('amplify.client_code'), ['MW', 'RHS'])
                ? 'getOrder'
                : 'get_order.php';

            $response = $this->post("/{$url}", $payload);

            return $this->adapter->getOrderList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getOrderList();
        }
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderDetail(array $orderInfo = []): Order
    {
        try {
            $order_number = $orderInfo['order_number'] ?? null;
            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'OrderNumber' => $order_number,
                    'LookupType' => ErpApiService::LOOKUP_HISTORICAL_ORDER,
                ],
            ];

            $response = $this->post('/getOrder', $payload);

            return $this->adapter->getOrderDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getOrderDetail();
        }
    }

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

            $url = match (config('amplify.client_code')) {
                'ACT', 'MW', 'PLS' => 'createOrder',
                default => 'getOrderTotal',
            };

            $response = $this->post("/{$url}", $payload);

            if (config('amplify.erp.use_amplify_shipping')) {
                $responseBackEnd = $this->getOrderTotalUsingBackend();

                $totalOrderValue = $response['Order'][0]['TotalOrderValue'] ?? 0;
                $salesTaxAmount = $response['Order'][0]['SalesTaxAmount'] ?? '0.00';
                $hazMatCharge = $response['Order'][0]['HazMatCharge'] ?? '0.00';

                $freightAmount = $responseBackEnd['Order'][0]['FreightAmount'] ?? '0.00';
                $freightRate = $responseBackEnd['Order'][0]['FreightRate'] ?? [];

                $mergedResponse = [
                    'Order' => [
                        [
                            'OrderNumber' => '',
                            'TotalOrderValue' => $totalOrderValue,
                            'SalesTaxAmount' => $salesTaxAmount,
                            'FreightAmount' => $freightAmount,
                            'FreightRate' => $freightRate,
                            'HazMatCharge' => $hazMatCharge,
                        ],
                    ],
                ];

                return $this->adapter->getOrderTotal($mergedResponse);
            }

            return $this->adapter->getOrderTotal($response);
        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getOrderTotal();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | QUOTATION FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a quotation in the FACTS ERP
     */
    public function createQuotation(array $orderInfo = []): CreateQuotationCollection
    {
        try {
            $order = $orderInfo['order'] ?? [];
            $items = $orderInfo['items'] ?? [];
            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'CustomerEmail' => $order['customer_email'] ?? $this->getCustomerDetail()->CustomerEmail,
                    'CarrierCode' => $order['carrier_code'] ?? 'UPS',
                    'PaymentType' => $order['payment_type'] ?? 'Standard',
                    'PoNumber' => $order['customer_order_ref'] ?? '',
                    'RequestedShipDate' => $order['requested_ship_date'] ?? '',
                    'ShipToNumber' => $order['ship_to_number'] ?? '',
                    'ShipToAddress1' => $order['ship_to_address1'] ?? '',
                    'ShipToAddress2' => $order['ship_to_address2'] ?? '',
                    'ShipToAddress3' => $order['ship_to_address3'] ?? '',
                    'ShipToCity' => $order['ship_to_city'] ?? '',
                    'ShipToCntryCode' => $order['ship_to_country_code'] ?? '',
                    'ShipToState' => $order['ship_to_state'] ?? '',
                    'ShipToZipCode' => $order['ship_to_zip_code'] ?? '',
                    'OrderType' => $order['order_type'] ?? 'T',
                    'ReturnType' => $order['return_type'] ?? 'D',
                    'CardToken' => $order['card_token'] ?? '',
                    'WarehouseID' => $order['warehouse_id'] ?? '',
                    'Items' => $items,
                ],
            ];

            $response = $this->post('/createOrder', $payload);

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
            $fromEntryDate = $filters['start_date'] ?? null;
            $toEntryDate = $filters['end_date'] ?? null;
            $maxRecords = $filters['limit'] ?? null;
            $restartPoint = $filters['restartPoint'] ?? null;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'FromEntryDate' => $fromEntryDate,
                    'ToEntryDate' => $toEntryDate,
                    'MaxRecords' => $maxRecords,
                    'RestartPoint' => $restartPoint,
                ],
            ];

            $url = in_array(config('amplify.client_code'), ['MW', 'RHS'])
                ? 'getQuote'
                : 'get_quote.php';

            $response = $this->post("/{$url}", $payload);

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
            $quote_number = $orderInfo['quote_number'] ?? null;
            $customer_number = $this->getCustomerNumber($orderInfo);
            $shippingList = [];

            if (! empty($orderInfo['GetShipVias']) && ! empty($customer_number)) {
                $customerList = $this->getCustomerList([
                    'customer_start' => $customer_number,
                    'customer_end' => $customer_number,
                    'GetShipVias' => $orderInfo['GetShipVias'],
                ]);

                if (! empty($customerList)) {
                    $shippingList = $customerList[0]['ShipVias'];
                }
            }

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'QuoteNumber' => $quote_number,
                ],
            ];

            $url = in_array(config('amplify.client_code'), ['MW', 'RHS'])
                ? 'getQuote'
                : 'get_quote.php';

            $response = $this->post("/{$url}", $payload);

            if (! empty($response['Quotes']) && ! empty($shippingList)) {
                $response['Quotes'][0] = $response['Quotes'][0] + ['shippingList' => $shippingList];
            }

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
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'Customer' => $customer_number,
                ],
            ];

            $response = $this->post('/arSummary', $payload);

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
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $invoice_number = $filters['invoice_number'] ?? null;

            $invoice_status = $filters['invoice_status'] ?? null;

            $to_entry_date = $filters['to_entry_date'] ?? null;

            $from_entry_date = $filters['from_entry_date'] ?? null;

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'InvoiceNumber' => $invoice_number,
                    'InvoiceStatus' => $invoice_status,
                    'ToEntryDate' => $to_entry_date,
                    'FromEntryDate' => $from_entry_date,
                ],
            ];

            $url = match (config('amplify.client_code')) {
                'MW' => 'arInvoice',
                'RHS' => 'opArInvoice',
                default => 'op_ar_invoice.php',
            };

            $response = $this->post("/{$url}", $payload);

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
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $invoice_number = $filters['invoice_number'] ?? null;

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'InvoiceNumber' => $invoice_number,
                ],
            ];

            $url = match (config('amplify.client_code')) {
                'MW' => 'arInvoice',
                'RHS' => 'opArInvoice',
                default => 'op_ar_invoice.php',
            };

            $response = $this->post("/{$url}", $payload);

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
        try {
            $invoices = $paymentInfo['invoices'] ?? [];
            $payment = $paymentInfo['paymentInfo'] ?? [];
            $type = $paymentInfo['type'] ?? [];
            $customer_number = $paymentInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
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
            $type = $noteInfo['type'] ?? 'SOEH';
            $note_number = $noteInfo['noteNumber'] ?? '';
            $order_number = $noteInfo['orderNumber'];
            $note = $noteInfo['note'] ?? '';

            $payload = [
                'content' => [
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
            $override_date = $filters['override_date'] ?? null;
            $promo_type = $filters['promo_type'] ?? 'O';
            $promo = $filters['promo'] ?? '';

            $payload = [
                'content' => [
                    'OverrideDate' => $override_date,
                    'PromoType' => $promo_type,
                    'Promo' => $promo,
                ],
            ];

            $response = $this->post('/getPromotion', $payload);

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
            $action = $filters['action'] ?? 'SPECIFIC';
            $override_date = $filters['override_date'] ?? null;
            $promo_type = $filters['promo_type'] ?? 'O';
            $promo = $filters['promo'];

            $payload = [
                'content' => [
                    'Action' => $action,
                    'OverrideDate' => $override_date,
                    'PromoType' => $promo_type,
                    'Promo' => $promo,
                ],
            ];

            $response = $this->post('/getPromotion', $payload);

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
            $contact_email = $orderInfo['email_address'] ?? null;
            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

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
     * This API is to get customer Accounts Receivables information from the FACTS ERP
     *
     * @throws \Exception
     */
    public function getCylinders(array $filters = []): CylinderCollection
    {
        try {
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $query = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                ],
            ];

            $response = $this->post('/getCylinders', $query);

            return $this->adapter->getCylinders($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCylinders();
        }
    }

    /**
     * This API is to get customer required document from ERP
     */
    public function getDocument(array $inputs = []): Document
    {
        try {

            $customer_number = $inputs['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $document_number = $inputs['document_number'] ?? null;
            $document_type = $inputs['document_type'] ?? 'I';

            $query = [
                'content' => [
                    'Customer' => $customer_number,
                    'DocNum' => $document_number,
                    'DocType' => $document_type,
                ],
            ];

            $url = match (config('amplify.client_code')) {
                'RHS' => 'document',
                default => 'pdf_document.php',
            };

            $response = $this->post("/{$url}", $query);

            return $this->adapter->getDocument($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getDocument();
        }
    }

    /**
     * This API is to get customer past sales items from the FACTS ERP
     *
     *
     * @throws FactsErpException
     */
    public function getPastItemList(array $filters = []): PastItemCollection
    {
        try {
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;
            $email_address = $filters['email_address'] ?? null;
            $item_number = $filters['item_number'] ?? null;
            $max_records = $filters['max_records'] ?? null;
            $restart_point = $filters['restart_point'] ?? null;

            $query = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'EmailAddress' => $email_address,
                    'ItemNumber' => $item_number,
                    'MaxRecords' => $max_records,
                    'RestartPoint' => $restart_point,
                ],
            ];

            $url = match (config('amplify.client_code')) {
                'RHS' => '/getPastSales',
                default => '/get_pastsales.php',
            };
            $response = $this->post($url, $query);

            return $this->adapter->getPastItemList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getPastItemList();
        }
    }

    private function getCustomerNumber($orderInfo)
    {
        if (! empty($orderInfo['customer_number'])) {
            return $orderInfo['customer_number'];
        }

        return $this->customerId();
    }

    /**
     * Fetch shipment tracking URL
     *
     * @throws FactsErpException
     */
    public function getTrackShipment(array $inputs = []): TrackShipmentCollection
    {
        try {

            $customer_number = $this->getCustomerDetail()->CustomerNumber;
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
