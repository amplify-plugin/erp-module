<?php

namespace Amplify\ErpApi\Services;

use Amplify\ErpApi\Adapters\AppriseErpAdapter;
use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\CreateQuotationCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\DocumentCollection;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Collections\InvoiceTransactionCollection;
use Amplify\ErpApi\Collections\OrderCollection;
use Amplify\ErpApi\Collections\OrderNoteCollection;
use Amplify\ErpApi\Collections\PastItemCollection;
use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Collections\ProductSyncCollection;
use Amplify\ErpApi\Collections\QuotationCollection;
use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\TrackShipmentCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Exceptions\ErpApiException;
use Amplify\ErpApi\Facades\ErpApi;
use Amplify\ErpApi\Guzzle\Middlewares\OrderCommunication;
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
use Amplify\ErpApi\Wrappers\OrderPODetails;
use Amplify\ErpApi\Wrappers\OrderTotal;
use Amplify\ErpApi\Wrappers\Quotation;
use Amplify\ErpApi\Wrappers\ShippingLocation;
use Amplify\ErpApi\Wrappers\ShippingLocationValidation;
use Amplify\ErpApi\Wrappers\TermsType;
use Amplify\System\Backend\Models\Country;
use Amplify\System\Backend\Models\Shipping;
use Amplify\System\Backend\Models\SystemConfiguration;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @property array $config
 */
class AppriseErpService implements ErpApiInterface
{
    use BackendShippingCostTrait;

    use ErpApiConfigTrait;

    private array $commonHeaders;

    public $systemId;

    public function __construct()
    {
        $this->adapter = new AppriseErpAdapter;

        $this->config = config('amplify.erp.configurations.apprise-erp');

        $this->systemId = $this->config['company_number'] ?? null;


        $this->commonHeaders = [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITY FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function adapter(): AppriseErpAdapter
    {
        return $this->adapter;
    }

    /**
     * @throws ErpApiException
     */
    public function refreshToken(bool $forceReset = false): void
    {
        $expirationAt = $this->config['expires_at'] ?? null;

        if (!$forceReset) {
            if (!empty($expirationAt) && now()->lessThan(CarbonImmutable::parse($expirationAt))) {
                return;
            }
        }

        $response = Http::withoutVerifying()
            ->asForm()
            ->withHeaders($this->commonHeaders)
            ->acceptJson()
            ->withBasicAuth(
                trim($this->config['client_id']),
                trim($this->config['client_secret']))
            ->post($this->config['token_url'], [
                'grant_type' => 'client_credentials',
                'user_id' => 'apiuser'
            ]);

        if (!$response->ok()) {
            throw new ErpApiException("Apprise-ERP token refresh failed. Error: [{$response->reason()}]");
        }

        $response = $response->json();

        if (is_string($response)) {
            $response = json_decode($response, true);
        }

        $this->config['access_token'] = $response['access_token'];
        $this->config['expires_at'] = (string)Carbon::createFromTimestamp($response['expires_in']);

        SystemConfiguration::setValue('erp', 'configurations.apprise-erp.access_token', $this->config['access_token'], 'string');
        SystemConfiguration::setValue('erp', 'configurations.apprise-erp.expires_at', $this->config['expires_at'], 'string');
    }

    /**
     * @throws ErpApiException
     */
    public function post(string $url, array $payload = []): array
    {
        $response = Http::appriseErp()
            ->withMiddleware(new OrderCommunication($this->orderId))
            ->baseUrl($this->config['url'])
            ->acceptJson()
            ->post($url, $payload);

        return $this->validate($response->json(), $url, $response->ok());

    }

    /**
     * @throws ErpApiException
     */
    public function get(string $url, array $payload = []): array
    {
        $response = Http::appriseErp()
            ->baseUrl($this->config['url'])
            ->acceptJson()
            ->get($url, $payload);

        return $this->validate($response->json(), $url, $response->ok());

    }


    /**
     * Validate the API call response
     *
     * @param mixed $response
     *
     * @throws ErpApiException|Exception
     */
    private function validate(?array $response, ?string $url = null, $status = true): array
    {
        try {

            if (!empty($response['code']) && $response['code'] != 'Success') {
                if (is_string($response['message'])) {
                    match ($response['message']) {
                        'Unauthorized' => throw new ErpApiException('ERP authentication token expired. Please try again later.', 403),
                        'invalid_grant' => throw new ErpApiException("Invalid ERP Credentials ({$response['message']})", 500),
                        'unsupported_grant_type' => throw new ErpApiException($response['message'], 500),
                        default => throw new ErpApiException('Unexpected Exception: ' . $response['message'], 500),
                    };
                }
            }

            return $response['data'] ?? [];

        } catch (ErpApiException $exception) {
            if ($exception->getCode() != 422) {
                $this->exceptionHandler($exception);
                return [];
            }
            return [
                'error' => $exception->getMessage(),
                'returnData' => $response['returnData'] ?? null,
            ];
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
     */
    public function createCustomer(array $attributes = []): CreateCustomer|Customer
    {
        try {

            return $this->adapter->createCustomer();

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->createCustomer();
        }
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
    public function getCustomerList(array $filters = []): CustomerCollection
    {
        try {

            return $this->adapter->getCustomerList();

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerList();
        }
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
    public function getCustomerDetail(array $filters = []): Customer
    {
        try {
            $customer_number = $this->customerId($filters);

            if ($customer_number == null) {
                throw new ErpApiException('Customer Code is missing.');
            }

            $payload = [
                'system_id' => $this->systemId
            ];

            $response = $this->get("/customer/{$customer_number}/information", $payload);

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
     * This API is to get customer ship to locations entity information from the CSD ERP
     *
     *
     * @todo
     *
     * @since 2024.12.8354871
     */
    public function getCustomerShippingLocationList(array $filters = []): ShippingLocationCollection
    {
        try {
            $customer_number = $this->customerId($filters);

            if ($customer_number == null) {
                throw new ErpApiException('Customer Code is missing.');
            }

            $payload = [
                'system_id' => $this->systemId
            ];

            $response = $this->get("/customer/{$customer_number}/information", $payload);

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
     * @since 2024.12.8354871
     */
    public function getProductPriceAvailability(array $filters = []): ProductPriceAvailabilityCollection
    {
        try {
            $items = $filters['items'] ?? [];

            $warehouses = array_filter(
                explode(',', $filters['warehouse'] ?? 'MAIN'),
                fn($item) => !empty($item)
            );

            $customer_number = $this->customerId($filters);

            $shipTo = $filters['ship_to_address'] ?? session('ship_to_address.ShipToNumber', ErpApi::getCustomerDetail()->DefaultShipTo ?? null);

            $payloads = [];

            foreach ($items as $item) {
                if (isset($payloads[$item['qty']])) {
                    $payloads[$item['qty']]['productCodes'][] = $item['item'];
                } else {
                    $payloads[$item['qty']] = [
                        'productCodes' => [$item['item']],
                        'displayFutureAvailabilityDetails' => true,
                        'customerCode' => $customer_number,
                        'trackingLevel' => 'location',
                        'salesLocationPrefix' => '',
                        'shippingLocationPrefix' => '',
                        'binName' => '',
                        'qty' => $item['qty'],
                        'date' => \now('UTC')->toIso8601ZuluString('m'),
                        'currency' => config('amplify.basic.global_currency', 'USD'),
                        'exchRate' => 1,
                    ];
                }
            }

            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($payloads) {
                foreach ($payloads as $qtyNumber => $payload) {
                    $pool->as($qtyNumber)
                        ->withOptions(Http::appriseErp()->getOptions())
                        ->baseUrl($this->config['url'])
                        ->post("/inventory/price-lookup", $payload);
                }
            });

            $collection = new ProductPriceAvailabilityCollection();

            $uoms = collect($items)->pluck('uom', 'item')->toArray();

            foreach ($responses as $qtyNumber => $response) {

                if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {

                    $res = $this->validate($response->json(), "/inventory/price-lookup", $response->ok());

                    $records = $res['records'] ?? [];

                    foreach ($records as $index => $record) {
                        $records[$index]['um'] = $uoms[$record['productCode']] ?? $record['um'] ?? 'each';
                        $records[$index]['qtyOnOrder'] = $qtyNumber;
                    }

                    $collection = $collection->merge($this->adapter->getProductPriceAvailability($records));
                }
            }

            return $collection;

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);
            return $this->adapter->getProductPriceAvailability();
        }
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     *
     * @since 2024.12.8354871
     *
     * @throw Exception
     */
    public function getProductSync(array $filters = []): ProductSyncCollection
    {
        try {

            return $this->adapter->getProductSync();

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
     * This API is to create an order in the CSD ERP
     *
     * @todo
     *
     * @since 2024.12.8354871
     */
    public function createOrder(array $orderInfo = []): Order
    {

        try {

            $this->orderId = $orderInfo['id'] ?? null;

            if (empty($this->orderId)) {
                throw new ErpApiException('Order info is missing the Order Id.');
            }

            $credentials = $orderInfo['credentials'] ?? [];

            $defaultNote = collect($orderInfo['order_notes'] ?? [])->firstWhere('type', 'INT');

            $cardExpiry = str_split($credentials['card_expiry'] ?? '', 2);

            $items = $orderInfo['order_lines'] ?? [];

            $lines = [];

            foreach ($items as $index => $item) {
                $lines[] = [
                    "lineKey" => $index,
                    "productCode" => $item['product_code'],
                    "productDesc" => Str::trim(Str::limit($item['product']['product_name'], 35)),
                    "qtyOrdered" => $item['qty'],
                    "unitOfMeasureCode" => $item['unit_code'] ?? '',
                    "unitOfMeasure" => "",
                    "unitPrice" => floatval($item['customer_price']),
//                    "notes" => $item['additional_info']['notes'] ?? '',
//                    "remarks" => $item['additional_info']['remarks'] ?? '',
//                    "remarksDocuments" => $item['additional_info']['remarksDocuments'] ?? '',
//                    "dropShip" => false,
//                    "custProdCode" => "",
                ];
            }

            $countryModel = Country::findByISO($orderInfo['customer_country_code'] ?? '');

            $phone = substr(preg_replace('/\D+/', '', $orderInfo['phone'] ?? ''), -10) ?: '';
            $shipToPhone = substr(preg_replace('/\D+/', '', $orderInfo['ship_to_phone'] ?? ''), -10) ?: '';

            // Strategy 1 (explicit ship-to): attach the tax information of the matching
            // custShipto record. When the ship-to record cannot be matched, the fields
            // are omitted so the ERP applies its own ship-to defaults (no mixed data).
            $taxFields = [];

            if (!empty($orderInfo['ship_to_name']) || !empty($orderInfo['ship_to_address_1'])) {
                try {
                    $shipToLocation = $this->getCustomerShippingLocationList([
                        'customer_number' => $orderInfo['customer_code'] ?? null,
                    ])->first(fn ($location) => $location->ShipToNumber == ($orderInfo['ship_to_number'] ?? null));

                    if ($shipToLocation !== null) {
                        if (!empty($shipToLocation->StateTaxExempt)) {
                            $taxFields['stateTaxExemptNum'] = $shipToLocation->StateTaxExempt;
                        }

                        if ($shipToLocation->TaxExempt !== null) {
                            $taxFields['taxExempt'] = (bool)$shipToLocation->TaxExempt;
                        }

                        $jurisdictionCode = $this->config['jurisdiction_map'][trim((string)$shipToLocation->JurisdictionKey)] ?? null;

                        if ($jurisdictionCode !== null) {
                            $taxFields['taxJurisdiction'] = $jurisdictionCode;
                        }
                    }
                } catch (Exception $taxException) {
                    $this->exceptionHandler($taxException);
                }
            }

            $payload = [
                "viewErrorsOnly" => true,
                "importAndValidate" => true,
                "overridePrice" => false,
                "language" => "en",
                "headers" => [
                    [
                        "customerCode" => (string)$orderInfo['customer_code'],
                        "customerName" => $orderInfo['customer_name'] ?? '',
                        "phoneNumber" => $phone,
                        "orderClass" => "Sale",
                        "customerPONumber" => $orderInfo['po_number'] ?? "",
                        "notes" => $defaultNote['note'] ?? '',
                        "remarks" => "",
                        "orderCreateInput" => "",
                        "customerBillToName" => $orderInfo['customer_name'] ?? '',
                        "customerBillToAddress1" => $orderInfo['customer_address_1'] ?? '',
                        "customerBillToAddress2" => $orderInfo['customer_address_2'] ?? '',
                        "customerBillToCity" => $orderInfo['customer_city'] ?? '',
                        "customerBillToState" => $orderInfo['customer_state'] ?? '',
                        "customerBillToZipcode" => $orderInfo['customer_zip_code'] ?? '',
                        "customerBillToCountry" => $orderInfo['customer_country_code'] ? $countryModel->iso3 : '',
                        "customerShipToCode" => '',
//                        "customerShipToCode" => $orderInfo['ship_to_number'] ?? '',
                        "customerShipToName" => $orderInfo['ship_to_name'] ?? '',
                        "customerShipToAddress1" => $orderInfo['ship_to_address_1'] ?? '',
                        "customerShipToAddress2" => $orderInfo['ship_to_address_2'] ?? '',
                        "customerShipToCity" => $orderInfo['ship_to_city'] ?? '',
                        "customerShipToState" => $orderInfo['ship_to_state'] ?? '',
                        "customerShipToZipcode" => $orderInfo['ship_to_zip_code'] ?? '',
                        "customerShipToCountry" => $orderInfo['ship_to_country_code'] ? $countryModel->iso3 : '',
                        "orderDate" => now()->toIso8601ZuluString('m'),
                        "requiredDate" => now()->toIso8601ZuluString('m'),
                        "shippingLocation" => $orderInfo['order_warehouse'] ?? '',
                        "shippingMethod" => $orderInfo['ship_to_method'] ?? '',
                        "billToAddress3" => $orderInfo['customer_address_3'] ?? '',
                        "shipToAddress3" => $orderInfo['ship_to_address_3'] ?? '',
                        "billToEmail" => $orderInfo['email'] ?? '',
                        "customerShipToPhone" => $shipToPhone,
                        "customerBillToPhone" => $phone,
                        "enteredCurrency" => config('amplify.basic.global_currency', 'USD'),
                        "lines" => $lines,
                        "saleType" => "web",
                        "salesLocation" => $orderInfo['order_warehouse'] ?? '',
                        "manualHold" => true,
                        "ecommSaveCC" => false,
                        "prepaidOrder" => false,
                        "charges" => [],

                        "paymentAcctID" => $credentials['card_token'] ?? '',
                        "ccLast4" => $credentials['card_number'] ?? '',
                        "ccExpMonth" => $cardExpiry[0] ? intval($cardExpiry[0]) : null,
                        "ccExpYear" => $cardExpiry[1] ? intval($cardExpiry[1]) : null,
                        "ccClaimTicket" => true,
                        "creditCardType" => $credentials['card_type'] ?? '',
                        "creditCardName" => $credentials['card_holder_name'] ?? '',
                        "termsCode" => $credentials['card_type'] ?? ''
                    ],
                ],
            ];

            $payload['headers'][0] += $taxFields;

            $response = $this->post('/orders', $payload);

            $response = array_merge($response, ['orderInfo' => $orderInfo]);

            $order = $this->adapter->createOrder($response);

            $this->orderId = null;

            return $order;

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
            $customer_number = $this->customerId($filters);

            $response = $this->get('/open-orders/sales-rep');

            $orders = $response['orders'] ?? [];

            // The ERP returns every sales rep order, scope the list to the logged in customer
            if (!empty($customer_number)) {
                $orders = array_values(array_filter(
                    $orders,
                    fn ($order) => ($order['custCode'] ?? null) == $customer_number
                ));
            }

            return $this->adapter->getOrderList(['orders' => $orders]);

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

            $response = !empty($order_number)
                ? $this->get("/orders/{$order_number}/information")
                : [];

            return $this->adapter->getOrderDetail($response);

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getOrderDetail();
        }
    }

    /**
     * This API is to get details of an order total and tax,
     *
     * @throws Exception
     */
    public function getOrderTotal(array $orderInfo = []): OrderTotal
    {
        try {

            $customer_number = $this->customerId($orderInfo);

            $customer = ErpApi::getCustomerDetail(['customer_number' => $customer_number]);
            /*
            $billToCountry = $this->countryByIso2($orderInfo['bill_to_country_code'] ?? $customer->CustomerCountry ?? '');
            $shipToCountry = $this->countryByIso2($orderInfo['ship_to_country_code'] ?? $customer->CustomerCountry ?? '');

            $items = $orderInfo['items'] ?? [];

            $orderLines = [];

            foreach ($items as $index => $item) {
                $orderLines[] = [
                    "lineKey" => $index,
                    "productCode" => $item['product_code'],
                    "productDesc" => $item['product_name'],
                    "qtyOrdered" => $item['quantity'],
                    "unitOfMeasureCode" => $item['uom'] ?? '',
                    "unitOfMeasure" => "",
                    "unitPrice" => floatval($item['unitprice']),
                    "cancelBackorder" => !$item['product_back_order'],
                    "notes" => $item['additional_info']['notes'] ?? '',
                    "dropShip" => false,
                    "custProdCode" => "",
                ];
            }

            $payload = [
                "viewErrorsOnly" => true,
                "importAndValidate" => true,
                "overridePrice" => false,
                "language" => "en",
                "headers" => [
                    [
                        "customerCode" => (string)$customer_number,
                        "customerName" => $customer->CustomerName,
                        "phoneNumber" => $customer->CustomerPhone,
                        "orderClass" => "Quote",
                        "customerPONumber" => $orderInfo['customer_order_ref'] ?? null,
                        "notes" => "test notes",
                        "orderCreateInput" => "",
                        "customerBillToCode" => "",
                        "customerBillToName" => $customer->CustomerName,
                        "customerBillToAddress1" => $customer->CustomerAddress1,
                        "customerBillToAddress2" => $customer->CustomerAddress2,
                        "customerBillToCity" => $customer->CustomerCity,
                        "customerBillToState" => $customer->CustomerState,
                        "customerBillToZipcode" => $customer->CustomerZipCode,
                        "customerBillToCountry" => $billToCountry?->iso3 ?? '',
                        "customerShipToCode" => '',
                        "customerShipToName" => $orderInfo['shipping_name'] ?? '',
                        "customerShipToAddress1" => $orderInfo['ship_to_address1'] ?? '',
                        "customerShipToAddress2" => $orderInfo['ship_to_address2'] ?? '',
                        "customerShipToCity" => $orderInfo['ship_to_city'] ?? '',
                        "customerShipToState" => $orderInfo['ship_to_state'] ?? '',
                        "customerShipToZipcode" => $orderInfo['ship_to_zip_code'] ?? '',
                        "customerShipToCountry" => $shipToCountry?->iso3 ?? '',
                        "orderDate" => now()->toIso8601ZuluString('m'),
                        "requiredDate" => now()->toIso8601ZuluString('m'),
                        "shippingLocation" => 'IN1',
                        "shippingMethod" => $orderInfo['shipping_method'] ?? 'AT',
                        "billToAddress3" => $customer->CustomerAddress3,
                        "shipToAddress3" => $orderInfo['ship_to_address3'] ?? '',
                        "billToEmail" => $customer->CustomerEmail,
                        "customerShipToPhone" => $orderInfo['phone_number'] ?? $customer->CustomerPhone,
                        "customerBillToPhone" => $customer->CustomerPhone,
                        "enteredCurrency" => $orderInfo['currency'] ?? config('amplify.basic.global_currency', 'USD'),
                        "lines" => $orderLines,
                        "saleType" => "web",
                        "salesLocation" => "IN1",
                        "manualHold" => false,
                        "ccClaimTicket" => false,
                        "ecommSaveCC" => false,
                        "prepaidOrder" => false,
                        "charges" => []

                    ],
                ],
            ];

            $response = $this->post('/orders', $payload);

            if (config('amplify.erp.use_amplify_shipping')) {
                $response['shipping'] = $this->getOrderTotalUsingBackend($payload);
            }

            $response['items'] = $items;*/

            $response = $this->getOrderTotalUsingBackend($orderInfo);

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
     * This API is to create a quotation in the CSD ERP
     */
    public function createQuotation(array $orderInfo = []): CreateQuotationCollection
    {
        try {

            $customer_number = $this->customerId($orderInfo);

            $customer = ErpApi::getCustomerDetail(['customer_number' => $customer_number]);

            $items = $orderInfo['items'] ?? [];

            $orderLines = [];

            foreach ($items as $index => $item) {
                $orderLines[] = [
                    "orderKey" => 0,
                    "lineKey" => $index,
                    "productCode" => $item['product_code'],
                    "productDesc" => $item['product_name'],
                    "qtyOrdered" => $item['quantity'],
                    "unitOfMeasureCode" => "",
                    "unitOfMeasure" => "",
                    "unitPrice" => $item['unitprice'],
                    "cancelBackorder" => !$item['product_back_order'],
                ];
            }


            $payload = [
                "viewErrorsOnly" => true,
                "importAndValidate" => true,
                "overridePrice" => false,
                "language" => "en",
                "headers" => [
                    [
                        "orderKey" => 0,
                        "customerCode" => (string)$customer_number,
                        "customerName" => $customer->CustomerName,
                        "phoneNumber" => $customer->CustomerPhone,
                        "orderClass" => "Quote",
//                        "orderClass" => "Sale" -> order
                        "customerPONumber" => $orderInfo['customer_order_ref'] ?? null,
//                        "notes" => "test",
                        "orderCreateInput" => " ",
                        "customerBillToCode" => '1175-0001',
//                        "customerBillToCode" => $customer->CustomerNumber,
                        "customerBillToName" => $customer->CustomerName,
                        "customerBillToAddress1" => $customer->CustomerAddress1,
                        "customerBillToAddress2" => $customer->CustomerAddress2,
                        "customerBillToCity" => $customer->CustomerCity,
                        "customerBillToState" => $customer->CustomerState,
                        "customerBillToZipcode" => $customer->CustomerZipCode,
                        "customerBillToCountry" => $customer->CustomerCountry,
                        "customerShipToCode" => '1175-0001',
                        "customerShipToName" => $orderInfo['shipping_name'] ?? '',
                        "customerShipToAddress1" => $orderInfo['ship_to_address1'] ?? '',
                        "customerShipToAddress2" => $orderInfo['ship_to_address2'] ?? '',
                        "customerShipToCity" => $orderInfo['ship_to_city'] ?? '',
                        "customerShipToState" => $orderInfo['ship_to_state'] ?? '',
                        "customerShipToZipcode" => $orderInfo['ship_to_zip_code'] ?? '',
                        "customerShipToCountry" => $orderInfo['ship_to_country_code'] ?? '',
                        "orderDate" => now()->toIso8601ZuluString('m'),
                        "shippingMethod" => $orderInfo['shipping_method'] ?? '',
                        "billToAddress3" => $customer->CustomerAddress3,
                        "shipToAddress3" => $orderInfo['ship_to_address3'] ?? '',
                        "billToEmail" => $customer->CustomerEmail,
                        "customerShipToPhone" => $orderInfo['phone_number'] ?? $customer->CustomerPhone,
                        "customerBillToPhone" => $customer->CustomerPhone,
                        "enteredCurrency" => $orderInfo['currency'] ?? config('amplify.basic.global_currency', 'USD'),
                        "lines" => $orderLines,
                    ],
                ],
            ];

            $response = $this->post('/orders', $payload);

            return $this->adapter->createQuotation($response);


        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->createQuotation();
        }
    }

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     *
     * @done mapping pending
     */
    public function getQuotationList(array $filters = []): QuotationCollection
    {
        try {
            return $this->adapter->getQuotationList();
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

            return $this->adapter->getQuotationDetail();

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getQuotationDetail();
        }
    }

    /**
     * This API is to get customer Accounts Receivables information from the CSD ERP
     *
     * @done
     */
    public function getCustomerARSummary(array $filters = []): CustomerAR
    {
        try {

            return $this->adapter->getCustomerARSummary();
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerARSummary();
        }
    }

    /**
     * This API is to get customer Accounts Receivables Open Invoices data from the CSD ERP
     */
    public function getInvoiceList(array $filters = []): InvoiceCollection
    {
        try {
            return $this->adapter->getInvoiceList();

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
     * This API is to get customer AR Open Invoice data from the CSD ERP.
     */
    public function getInvoiceDetail(array $filters = []): Invoice
    {
        try {

            return $this->adapter->getInvoiceDetail();

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
            return $this->adapter->createPayment();
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

            return $this->adapter->createOrUpdateNote();
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
        try {
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

            return $this->adapter->contactValidation();

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->contactValidation();
        }
    }

    /**
     * This API is to get customer required document from ERP
     * Get printable document (Invoice / Order / Quote) from IDM
     */
    public function getDocument(array $inputs = []): Document
    {
        try {
            return $this->adapter->getDocument();

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getDocument();
        }
    }

    /**
     * This API is to get customer past sales items from the CSD ERP
     */
    public function getPastItemList(array $filters = []): PastItemCollection
    {
        try {
            return $this->adapter->getPastItemList();

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getPastItemList();
        }
    }

    /**
     * This API is to get shipping tracking URL
     */
    public function getTrackShipment(array $inputs = []): TrackShipmentCollection
    {
        try {

            $payload = [
                'orders' => $inputs['order_number'] ?? [],
            ];

            $response = $this->get('/trackingnumbers', $payload);

            return $this->adapter->getTrackShipment($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getTrackShipment();
        }
    }

    /**
     * To get customer terms type
     * Terms are CRCD-credit card only or COD-cash only
     * Or CIA-ACH only, MAN-blocked
     */
    public function getTermsType(array $filters = []): TermsType
    {
        try {

            $customer_number = $this->customerId($filters);

            $customer = ErpApi::getCustomerDetail(['customer_number' => $customer_number]);

            $response = $customer->getRawContent();

            return $this->adapter->getTermsType($response['customerAr'] ?? []);

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getTermsType();
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
        $customer_number = $this->customerId($attributes);

        $contact_code = $attributes['contact_code'] ?? '';

        $action = $attributes['action'] ?? 'add'; // add/chg

        $customer = $this->getCustomerDetail(['customer_number' => $customer_number]);

        try {

            $nameParts = split_full_name($attributes['name'] ?? '');

            $fields['firstnm'] = strtoupper($nameParts['first']);
            $fields['middlenm'] = '';
            $fields['lastnm'] = strtoupper($nameParts['last']);
            $fields['cotitle'] = $attributes['account_title_code'] ?? '';
            $fields['comment'] = 'Created By Amplify. Id:' . ($attributes['id'] ?? '');
            $fields['priority'] = 1;
            $fields['salutation'] = '';
            $fields['groupcd'] = $attributes['groupcd'] ?? null;
            $fields['contacttype'] = strtoupper($attributes['account_title_code'] ?? '');
            $fields['workphoneno'] = $attributes['phone'] ?? null;
            $fields['homephoneno'] = $attributes['phone'] ?? null;
            $fields['cellphoneno'] = $attributes['phone'] ?? null;
            $fields['workemailaddr'] = $attributes['email'] ?? null;
            $fields['homeemailaddr'] = $attributes['email'] ?? null;
            $fields['addr1'] = $customer->CustomerAddress1 ?? null;
            $fields['addr2'] = $customer->CustomerAddress2 ?? null;
            $fields['city'] = $customer->CustomerCity ?? null;
            $fields['faxnumber'] = '';
            $fields['state'] = $customer->CustomerState ?? null;
            $fields['zipcd'] = $customer->CustomerZipCode ?? null;
            $fields['addtiearsc'] = (string)$customer_number;
            //            $fields['addtiearss'] = "{$customer_number},{$customer->DefaultShipTo}";

            $tMnTt = [];

            $count = 1;

            foreach ($fields as $field => $value) {
                $tMnTt[] = [
                    'setNo' => 1,
                    'seqNo' => $count,
                    'key1' => $action == 'chg' ? $contact_code : '',
                    'key2' => '',
                    'updateMode' => $action,
                    'fieldName' => $field,
                    'fieldValue' => (string)$value,
                ];
                $count++;
            }

            $payload = [
                'companyNumber' => $this->systemId,
                'tMntTt' => ['t-mnt-tt' => $tMnTt],
            ];

            $response = $this->post('/sxapicamcontactmnt', $payload);

            if (isset($response['error'])) {
                return $this->adapter()->getContactDetail($response);
            }

            $contact_code = preg_replace('/Set#: (\d*) Update Successful, Cono: 1 Contact ID: ([\d+])/', '$2', trim($response['returnData'] ?? ''));

            $contactData = [
                'customer_number' => $customer_number,
                'contact_code' => $contact_code,
            ];

            return $this->getContactDetail($contactData);

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->createUpdateContact();
        }
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
        try {

            return $this->adapter->getContactList();

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getContactList();
        }
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
        try {

            return $this->adapter->getContactDetail();
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getContactDetail();
        }
    }

    public function getNotesList(array $inputs = []): OrderNoteCollection
    {
        try {

            return $this->adapter->getNotesList();

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getNotesList();
        }
    }

    public function getPODetails($inputs = []): OrderPODetails
    {
        try {

            $response = $this->get("/purchaseorder/{$inputs['po_number']}/information");

            return $this->adapter->getPODetails($response);

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getPODetails();
        }
    }

    public function getInvoiceTransaction(array $inputs = []): InvoiceTransactionCollection
    {
        try {

            return $this->adapter->getInvoiceTransaction();

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getInvoiceTransaction();
        }
    }

    /**
     * @throws ErpApiException
     */
    public function createUpdateCustomerPartNumber(array $inputs = []): array
    {
        $customer_number = $this->customerId($inputs);

        $payload = [
            'companyNumber' => $this->systemId,
            'customerNumber' => $customer_number,
            'productCode' => $inputs['customer_product_code'] ?? '',
            'alternateProductCode' => $inputs['item_number'] ?? '',
            'unitSell' => $inputs['item_uom'] ?? 'ea',
            'orderQuantity' => $inputs['min_qty'] ?? 1,
        ];

        $payload['updateType'] = $inputs['action'] == 'delete' ? 'd' : 'c';

        $response = $this->post('/sxapiiccustprodmnt', $payload);

        return [
            'success' => isset($response['outputCustomParameter']) && empty($response['outputCustomParameter']),
            'error' => $response['error'] ?? ''
        ];
    }

    /**
     * Get printable document (Invoice / Order / Quote) from IDM
     *
     * @throws ErpApiException|Exception
     */
    public function getPrintableDocument(array $inputs = []): DocumentCollection
    {
        try {
            return $this->adapter->renderPrintableDocument([]);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);
            return $this->adapter->renderPrintableDocument([]);
        }
    }


}
