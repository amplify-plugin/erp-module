<?php

namespace Amplify\ErpApi\Services;

use Amplify\ErpApi\Adapters\CsdErpAdapter;
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
use Amplify\ErpApi\Exceptions\CsdErpException;
use Amplify\ErpApi\Facades\ErpApi;
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
use Amplify\ErpApi\Wrappers\TermsType;
use Amplify\System\Backend\Models\Shipping;
use Amplify\System\Backend\Models\SystemConfiguration;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @property array $config
 */
class CsdErpService implements ErpApiInterface
{
    use BackendShippingCostTrait;
    use ErpApiConfigTrait;

    private array $commonHeaders;

    private $companyNumber;

    private $operatorInit;

    /**
     * @throws CsdErpException
     */
    public function __construct()
    {
        $this->adapter = new CsdErpAdapter;

        $this->config = config('amplify.erp.configurations.csd-erp');

        $this->companyNumber = intval($this->config['company_number'] ?? '1');

        $this->operatorInit = $this->config['operator_init'] ?? 'sys';

        $this->commonHeaders = [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
        ];

        $this->refreshToken();
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITY FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function adapter(): CsdErpAdapter
    {
        return $this->adapter;
    }

    /**
     * @throws CsdErpException
     */
    private function refreshToken(): void
    {
        $expirationAt = $this->config['expires_at'] ?? null;

        if ($expirationAt == null || now()->gt(CarbonImmutable::parse($expirationAt))) {

            $response = Http::withoutVerifying()->asForm()
                ->withHeaders($this->commonHeaders)
                ->post($this->config['token_url'], [
                    'grant_type' => 'password',
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'username' => $this->config['username'],
                    'password' => $this->config['password'],
                ]);

            if ($response->ok()) {
                $response = $response->json();
            } else {
                $this->validate($response->json());
            }

            $this->config['access_token'] = $response['access_token'];
            $this->config['expires_at'] = (string)now()->addSeconds($response['expires_in']);

            SystemConfiguration::setValue('erp', 'configurations.csd-erp.access_token', $response['access_token'], 'string');
            SystemConfiguration::setValue('erp', 'configurations.csd-erp.expires_at', (string)now()->addSeconds($response['expires_in']), 'string');
        }
    }

    /**
     * @throws CsdErpException
     */
    public function post(string $url, array $payload = []): array
    {
        if (isset($payload['customerNumber'])) {
            $payload['customerNumber'] = intval($payload['customerNumber']);
        }

        $attchedPayload = ['request' => $payload];

        $baseUrl = $this->config['url'];

        if ($url == '/proxy/FetchWhere') {
            $baseUrl = str_replace('web/sxapirestservice', 'rest/serviceinterface', $baseUrl);
            $attchedPayload = $payload;
        }

        $response = Http::csdErp()
            ->baseUrl($baseUrl)
            ->post($url, $attchedPayload)
            ->json();

        if ($url == '/proxy/FetchWhere') {
            return $response;
        }

        return $this->validate($response, $url);

    }

    /**
     * Validate the API call response
     *
     * @param mixed $response
     *
     * @throws CsdErpException|Exception
     */
    private function validate(array $response, ?string $url = null): array
    {
        try {

            if (isset($response['error'])) {
                if (is_string($response['error'])) {
                    match ($response['error']) {
                        'Unauthorized' => throw new CsdErpException('Unauthorized', 403),
                        'invalid_grant' => throw new CsdErpException("Invalid ERP Credentials ({$response['error_description']})", 500),
                        'unsupported_grant_type' => throw new CsdErpException($response['error_description'], 500),
                        default => throw new CsdErpException('Unexpected Exception: ' . $response['error'], 500),
                    };
                }
            }

            $response = $response['response'] ?? [];

            if (!empty($response['cErrorMessage'])) {
                $friendlyMessage = $this->mapErpErrorMessage($response['cErrorMessage']);
                throw new CsdErpException($friendlyMessage, 422);
            }

            unset($response['cErrorMessage']);

            return $response;

        } catch (CsdErpException $exception) {
            if ($exception->getCode() != 422) {
                $this->exceptionHandler($exception);
                return [];
            }
            return [
                'error' => $exception->getMessage()
            ];
        }
    }

    private function mapErpErrorMessage(string $rawMessage): string
    {
        $message = trim($rawMessage);

        // Case 1: Customer PO already exists
        if (preg_match('/Customer PO# Already Exists.*RequestCustPo:(\S+)/i', $message, $matches)) {
            $poNumber = $matches[1];
            return "PO Number ({$poNumber}) already exists in the system. Please enter a different PO number.";
        }

        // Default fallback
        return $message;
    }


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a new cash customer account
     *
     * @done untested
     *
     * @since 2024.12.8354871
     */
    public function createCustomer(array $attributes = []): CreateCustomer|Customer
    {
        try {
            $fields['currencyty'] = '';
            $fields['name'] = $attributes['customer_name'] ?? '';
            $fields['addr1'] = $attributes['address_1'] ?? null;
            $fields['addr2'] = $attributes['address_2'] ?? null;
            $fields['addr3'] = $attributes['address_3'] ?? null;
            $fields['city'] = $attributes['city'] ?? '';
            $fields['zipcd'] = $attributes['zip_code'] ?? '';
            $fields['state'] = $attributes['state'] ?? '';
            $fields['countrycd'] = strtolower($attributes['country_code'] ?? '');
            $fields['phoneno'] = '';
            $fields['faxphoneno'] = '';
            $fields['comment'] = '';
            $fields['siccd1'] = $attributes['siccd1'] ?? 0;
            $fields['siccd2'] = $attributes['siccd2'] ?? 0;
            $fields['siccd3'] = $attributes['siccd3'] ?? 0;
            $fields['statustype'] = $attributes['statustype'] ?? 'Active';
            $fields['user1'] = $attributes['contact'] ?? '';
            $fields['email'] = 'ACCOUNTING USE ONLY!  DO NOT MODIFY';

            $fields['credlim'] = '';
            $clientCode = config('amplify.client_code');
            $countryCode = strtolower($attributes['country_code'] ?? '');
            if ($clientCode === 'STV') {
                if ($countryCode !== 'us' && $countryCode !== 'ca') {
                    $fields['termstype'] = 'CIA';
                } else {
                    $fields['termstype'] = 'CRCD';
                }
            } else {
                $fields['termstype'] = 'CRCD';
            }
            $fields['pricetype'] = '2';
            $fields['shipviaty'] = 'UPSG';
            $fields['lookupnm'] = substr($attributes['customer_name'] ?? '', 0, 15);
            $fields['selltype'] = 'Y';
            $fields['taxablety'] = 'Y';
            $fields['pricecd'] = '1';

            $tMnTt = [];

            $count = 1;

            foreach ($fields as $field => $value) {
                $tMnTt[] = [
                    'setNo' => 1,
                    'seqNo' => $count,
                    'key1' => '', // customer code
                    'key2' => '', // customer shop-to code
                    'updateMode' => 'add', // add/chg
                    'fieldName' => $field,
                    'fieldValue' => (string)$value,
                ];
                $count++;
            }

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'tMntTt' => ['t-mnt-tt' => $tMnTt],
            ];

            $response = $this->post('/sxapiarcustomermnt', $payload);

            $customer_number = preg_replace('/Set#: (\d*) Update Successful, Cono: 1 Customer #: ([\d+])/', '$2', $response['returnData']);

            $response = [
                'customer_number' => $customer_number,
            ];

            return $this->getCustomerDetail($response);

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
            $limit = $filters['limit'] ?? 0;

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'includeInactiveCustomers' => true,
                'recordLimit' => $limit,
                'postalCode' => (string)($filters['zip_code'] ?? null),
            ];

            if (!empty($filters['customer_number'])) {
                $payload['customerNumber'] = $filters['customer_number'];
            }

            if (!empty($filters['customer_start'])) {
                $payload['customerNumber'] = $filters['customer_start'];
            }

            if (!empty($filters['customer_end'])) {
                $payload['customerNumber'] = $filters['customer_end'];
            }

            if (isset($filters['street_address'])) {
                $address = explode(' ', strtoupper($filters['street_address']), 5);
                foreach ($address as $index => $addr) {
                    $payload['keyWord' . ($index + 1)] = $addr;
                }
            }

            $response = $this->post('/sxapiargetcustomerlistv3', $payload);

            return $this->adapter->getCustomerList($response);

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
                throw new CsdErpException('Customer Code is missing.');
            }

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $customer_number,
                'shipTo' => '',
                'requestType' => 'general',
                'extraData' => '',
            ];

            $response = $this->post('/sxapisfgetcustomermasterv2', $payload);

            $response['customerNumber'] = $customer_number;

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
     * @done
     *
     * @since 2024.12.8354871
     */
    public function getShippingOption(array $data = []): ShippingOptionCollection
    {
        $options = Shipping::enabled()->get()->toArray();

        return $this->adapter->getShippingOption($options);
    }

    /**
     * @todo untestable
     */
    public function validateCustomerShippingLocation(array $filters = []): ShippingLocationValidation
    {
        try {

            $customer_number = $this->customerId($filters);

            $addressName = $filters['ship_to_name'] ?? '';
            $addressCode = $filters['ship_to_code'] ?? '';

            // taxi sing software in use
            $shipTo = [
                'streetaddr' => $filters['ship_to_address1'] ?? null,
                'streetaddr2' => $filters['ship_to_address2'] ?? null,
                'streetaddr3' => $filters['ship_to_address3'] ?? null,
                'city' => $filters['ship_to_city'] ?? null,
                'country' => $filters['ship_to_country_code'] ?? null,
                'state' => $filters['ship_to_state'] ?? null,
                'zipcd' => $filters['ship_to_zip_code'] ?? null,
                'addressoverfl' => true,
            ];

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $customer_number,
                'tInAddrValidation' => [
                    't-in-addr-validation' => [$shipTo],
                ],
            ];

            $response = $this->post('/sxapiaddressvalidation', $payload);

            $response['name'] = $addressName;
            $response['code'] = $addressCode;

            return $this->adapter->validateCustomerShippingLocation($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->validateCustomerShippingLocation();
        }
    }

    /**
     * This API is to get customer ship to locations entity information from the CSD ERP
     *
     *
     * @todo
     *
     * @since 2024.12.8354871
     */
    public function createCustomerShippingLocation(array $attributes = []): ShippingLocation
    {
        try {
            $customer_number = $this->customerId($attributes);

            $fields['name'] = $attributes['address_name'] ?? '';
            $fields['addr1'] = $attributes['address_1'] ?? null;
            $fields['addr2'] = $attributes['address_2'] ?? null;
            $fields['addr3'] = $attributes['address_3'] ?? null;
            $fields['city'] = $attributes['city'] ?? '';
            $fields['zipcd'] = $attributes['zip_code'] ?? '';
            $fields['state'] = Str::upper($attributes['state'] ?? '');
            $fields['countrycd'] = Str::upper($attributes['country_code'] ?? '');
            $fields['phoneno'] = $attributes['phone_1'] ?? '';
            $fields['email'] = $attributes['email_1'] ?? '';
            $fields['faxphoneno'] = '';
            $fields['statustype'] = $attributes['statustype'] ?? 'Active';

            $tMnTt = [];

            $count = 1;

            foreach ($fields as $field => $value) {
                $tMnTt[] = [
                    'setNo' => 1,
                    'seqNo' => $count,
                    'key1' => $customer_number,
                    'key2' => $attributes['address_code'] ?? '',
                    'updateMode' => 'add',
                    'fieldName' => $field,
                    'fieldValue' => (string)$value,
                ];
                $count++;
            }

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $customer_number,
                'tMntTt' => ['t-mnt-tt' => $tMnTt],
            ];

            $response = $this->post('/sxapiarcustomermnt', $payload);

            if (empty($response['returnData'])) {
                return $this->adapter->renderSingleCustomerShippingLocation([...$fields, 'shipto' => $attributes['address_code']]);
            }

            $search = [
                'address_code' => $attributes['address_code'],
            ];

            $addresses = $this->getCustomerShippingLocationList($search);

            if ($addresses->isEmpty()) {
                return $this->adapter->renderSingleCustomerShippingLocation([...$fields, 'shipto' => $attributes['address_code']]);
            }

            return $addresses->first();

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->renderSingleCustomerShippingLocation([]);
        }
    }

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

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $customer_number,
                'sort' => 'A'
            ];

            if (!empty($filters['address_name'])) {
                $payload['name'] = $filters['address_name'];
            }

            if (!empty($filters['city'])) {
                $payload['city'] = $filters['city'];
            }

            if (!empty($filters['address_code'])) {
                $payload['shipTo'] = $filters['address_code'];
            }

            if (!empty($filters['state'])) {
                $payload['state'] = $filters['state'];
            }

            if (!empty($filters['zip_code'])) {
                $payload['postalCode'] = $filters['zip_code'];
            }

            $response = $this->post('/sxapiargetshiptolistv4', $payload);

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

            $shipTo = $filters['ship_to_address'] ?? session('ship_to_address.ShipToNumber',ErpApi::getCustomerDetail()->DefaultShipTo ?? null);

            $reminder = ceil(count($items) / 3);

            $entries = [];

            foreach ($items as $itemIndex => $item) {
                foreach ($warehouses as $warehouseIndex => $warehouse) {
                    $entries[$itemIndex % $reminder][] = [
                        'seqno' => (900 + $itemIndex) . (600 + $warehouseIndex),
                        'whse' => $warehouse,
                        'qtyord' => $item['qty'] ?? 1,
                        'unit' => isset($item['uom']) ? $item['uom'] : 'ea',
                        'prod' => $item['item'],
                    ];
                }
            }

            $payloads = [];

            foreach ($entries as $entry) {
                $payloads[] = [
                    'companyNumber' => $this->companyNumber,
                    'operatorInit' => $this->operatorInit,
                    'customerNumber' => $customer_number,
                    'getPriceBreaks' => true,
                    'checkOtherWhseInventory' => true,
                    'shipTo' => $shipTo,
                    'tOemultprcinV2' => [
                        't-oemultprcinV2' => $entry,
                    ],
                    'tInfieldvalue' => [
                        't-infieldvalue' => [
                            'lineno' => 0,
                        ],
                    ],
                ];
            }

            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($payloads) {
                foreach ($payloads as $index => $payload) {

                    if (empty($payload['shipTo'])) {
                        unset($payload['shipTo']);
                    }

                    $pool->as($index)
                        ->withOptions(Http::csdErp()->getOptions())
                        ->baseUrl($this->config['url'])
                        ->post("/sxapioepricingmultiplev5", ['request' => $payload]);
                }
            });

            $collection = new ProductPriceAvailabilityCollection();

            foreach ($responses as $response) {
                if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                    $res = $this->validate($response->json());
                    $collection = $collection->merge($this->adapter->getProductPriceAvailability($res));
                }
            }

            return $collection;

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);
            return $this->adapter->getProductPriceAvailability();
        }
    }

    /**
     * Fetch past sales history from ERP using FetchWhere proxy call.
     *
     * Expected filters:
     * - prod / product : product code (required)
     * - year : two-digit or four-digit year (optional, defaults to current year)
     * - warehouses : comma separated warehouse list for CAN-DO (optional)
     * - batch_size : number (optional, defaults to 100)
     * - restart_row_id : string (optional)
     *
     * This calls the /proxy/FetchWhere endpoint with the payload shape the ERP expects.
     */
    public function getPastSalesHistory(array $filters = []): array
    {
        try {
            $prod = $filters['prod'] ?? $filters['product'] ?? null;

            if (empty($prod)) {
                throw new CsdErpException('Product code is missing.');
            }

            $year = $filters['year'] ?? date('Y');
            if (is_numeric($year) && intval($year) > 999) {
                $year = intval(substr((string)$year, -2));
            } else {
                $year = intval($year);
            }

            $warehouses = $filters['warehouses'] ?? 'MAIN,COR1,ORE1,CEL1';

            $customer_number = $this->customerId($filters);

            $whereClause = "smsew.cono = {$this->companyNumber} and smsew.yr = {$year} and smsew.prod = '{$prod}' and CAN-DO('{$warehouses}', smsew.whse)and smsew.custno = {$customer_number}";

            $payload = [
                'CompanyNumber' => $this->companyNumber,
                'Operator' => $this->operatorInit,
                'TableName' => 'smsew',
                'WhereClause' => $whereClause,
                'BatchSize' => $filters['batch_size'] ?? 100,
                'RestartRowID' => $filters['restart_row_id'] ?? '',
            ];

            $response = $this->post('/proxy/FetchWhere', $payload);
            $response['year'] = (int) $filters['year'] ?? date('Y');

            return $this->adapter->getPastSalesHistory($response);

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getPastSalesHistory();

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
            $itemStart = $filters['item_start'] ?? '';
            $itemEnd = $filters['item_end'] ?? '';
            $updatesOnly = $filters['updates_only'] ?? 'N';
            $processUpdates = $filters['process_updates'] ?? 'N';
            $maxRecords = $filters['limit'] ?? '';
            $restartPoint = ($filters['restart_point'] ?? isset($filters['restartPoint'])) ? $filters['restartPoint'] : '';

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'updateDate' => now()->format('Y-m-d'),
                'updateRecords' => $processUpdates == 'Y',
                'recordLimit' => $maxRecords,
            ];

            $response = $this->post('/sxapiicecatalogitemmasterv3', $payload);

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
     * @throws CsdErpException|Exception
     *
     * @since 2024.12.8354871
     */
    public function getWarehouses(array $filters = []): WarehouseCollection
    {
        try {

            $warehouses = Cache::remember('site-erp-warehouses', WEEK, function () {
                return \Amplify\System\Backend\Models\Warehouse::all();
            });

            foreach ($filters as $filter) {
                if (in_array(count($filter), [2, 3])) {
                    $warehouses = $warehouses->where(...$filter);
                }
            }

            return $this->adapter->getWarehouses($warehouses->toArray());

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getWarehouses();
        }
    }

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
            return $this->handleOrderSubmission($orderInfo);

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->createOrder();
        }
    }

    private function handleOrderSubmission(array $orderInfo = [])
    {
        if (config('amplify.client_code') === 'STV') {
            return $this->createOrderSteven($orderInfo);
        }

        return $this->createOrderDkLok($orderInfo);

    }

    /**
     * @throws CsdErpException
     */
    private function createOrderDkLok(array $orderInfo = []): Order
    {
        $order = $orderInfo['order'] ?? [];
        $items = $orderInfo['items'] ?? [];
        $orderRequest = $order['request'] ?? [];
        $customerNumber = $this->customerId($orderInfo);

        $orderLine = array_map(function ($item) {
            return [
                'itemnumber' => $item['ItemNumber'],
                'orderqty' => $item['OrderQty'],
                'unitofmeasure' => $item['UnitOfMeasure'],
                'warehouseid' => $item['WarehouseID'],
                'itemdesc1' => $item['ItemComment'],
                'shipinstrty' => $item['OrderQty'],
            ];
        }, $items);

        $warehouseId = ! empty($orderLine[0]['warehouseid']) ? $orderLine[0]['warehouseid'] : null;

        $noteText = trim($order['order_note'] ?? '');

        if (!empty($noteText)) {
            $cleanedNoteText = implode("\n", array_map('trim', explode("\n", $noteText)));
            $orderLine[] = [
                'itemdesc1' => $cleanedNoteText,
                'itemnumber' => '/',
                'lineitemtype' => 'C ',
            ];
        }

        $payload = [
            'companyNumber' => $this->companyNumber,
            'operatorInit' => $this->operatorInit,
            'tInputccdata' => [
                't-Inputccdata' => [],
            ],
            'tInputheaderdata' => [
                't-inputheaderdata' => [
                    [
                        'taxamount' => 0,
                        'authorizationamount' => 0,
                        'customerid' => '00000000000' . $customerNumber,
                        'ordersource' => 'WEB',
                        'carriercode' => $order['shipping_method'],
                        'paymenttype' => 'PO',
                        'ponumber' => $orderRequest['customer_order_ref'],
                        'shiptoaddr1' => $order['ship_to_address1'],
                        'shiptoaddr2' => $order['ship_to_address2'],
                        'shiptoaddr3' => $order['ship_to_address3'],
                        'shiptocontact' => $order['ship_to_name'],
                        'shiptocity' => $order['ship_to_city'],
                        'shiptocountry' => $order['ship_to_country_code'],
                        'shiptoname' => $order['ship_to_name'],
                        'shiptonumber' => $order['ship_to_number'],
                        'shiptostate' => $order['ship_to_state'],
                        'shiptophone' => $order['phone_number'],
                        'shiptophoneext' => '',
                        'shiptozip' => $order['ship_to_zip_code'],
                        'webtransactiontype' => 'LSF',
                        'ordertype' => $order['order_type'],
                        'warehouseid' => $warehouseId,
                    ],
                ],
            ],
            'tInputlinedata' => [
                't-inputlinedata' => $orderLine,
            ],
            'tInputheaderextradata' => [
                't-inputheaderextradata' => [
                    [
                        'fieldname' => 'placedby',
                        'fieldvalue' => $this->operatorInit,
                    ],
                    [
                        'fieldname' => 'email',
                        'fieldvalue' => $order['customer_email'],
                    ],
                ],
            ],
            'tInputlineextradata' => [
                't-inputlineextradata' => [],
            ],
            'tInfieldvalue' => [
                't-infieldvalue' => [],
            ],
        ];

        $response = $this->post('/sxapisfoeordertotloadv4', $payload);

        if (empty($data = $response['tOrdloadhdrdata']['t-ordloadhdrdata'][0])) {
            throw new CsdErpException('Something went wrong please try again');
        }

        $orderData = $this->getOrderDetail([
            'order_number' => $data['orderno'],
            'customer_number' => $customerNumber,
            'order_suffix' => $data['ordersuf'],
        ]);

        $orderData->OrderStatus = 'Accepted';

        return $orderData;
    }

    /**
     * @throws CsdErpException
     */
    private function createOrderSteven(array $orderInfo = []): Order
    {
        $order = $orderInfo['order'] ?? [];
        $items = $orderInfo['items'] ?? [];
        $customer_number = $this->customerId($orderInfo);
        $contact_code = $order['contact_code'] ?? null;
        $review_order_hold = $order['review_order_hold'] ?? 'V';

        $orderLine = array_map(function ($item) {
            return [
                'itemnumber' => $item['ItemNumber'],
                'orderqty' => $item['OrderQty'],
                'unitofmeasure' => $item['UnitOfMeasure'],
                'warehouseid' => $item['WarehouseID'],
                'itemdesc1' => $item['ItemComment'] ?? '',
                'shipinstrty' => $item['OrderQty'],
            ];
        }, $items);

        $noteText = '';
        $orderNote = trim($order['order_note'] ?? '');
        $internalNote = trim($order['internal_note'] ?? '');

        if (!empty($orderNote)) {
            $noteText .= "SEI Instructions: {$orderNote}";
        }

        if (!empty($internalNote)) {
            if (!empty($noteText)) {
                $noteText .= "\n\n* * * * * * * *\n\n";
            }
            $noteText .= "Customer Comments: {$internalNote}";
        }

        if (!empty($noteText)) {
            $cleanedNoteText = implode("\n", array_map('trim', explode("\n", $noteText)));
            $orderLine[] = [
                'itemdesc1' => $cleanedNoteText,
                'itemnumber' => '/',
                'lineitemtype' => 'C ',
            ];
        }

        if (!empty($order['wtdo_note'])) {
            // Clean leading/trailing spaces from each line of WTDO note
            $cleanedWtdoNote = implode("\n", array_map('trim', explode("\n", $order['wtdo_note'])));
            $orderLine[] = [
                'itemdesc1' => $cleanedWtdoNote,
                'itemnumber' => '/',
                'lineitemtype' => 'cx',
            ];
        }

        $tinfieldvalue = [];
        if (!empty($order['card_token']) && $order['payment_method'] == 'credit_card') {
            $tinfieldvalue = [
                [
                    'level' => 'SFOEOrderTotLoadV4',
                    'lineno' => 0,
                    'seqno' => 0,
                    'fieldname' => 'AuthAmt',
                    'fieldvalue' => $order['total_order_value'],
                ],
                [
                    'level' => 'SFOEOrderTotLoadV4',
                    'lineno' => 0,
                    'seqno' => 0,
                    'fieldname' => 'ProcPaymentType',
                    'fieldvalue' => $order['card_type'],
                ],
                [
                    'level' => 'SFOEOrderTotLoadV4',
                    'lineno' => 0,
                    'seqno' => 0,
                    'fieldname' => 'MerchantID',
                    'fieldvalue' => $order['merchant_id'],
                ],
                [
                    'level' => 'SFOEOrderTotLoadV4',
                    'lineno' => 0,
                    'seqno' => 0,
                    'fieldname' => 'CardNumber',
                    'fieldvalue' => $order['card_number'],
                ],
                [
                    'level' => 'SFOEOrderTotLoadV4',
                    'lineno' => 0,
                    'seqno' => 0,
                    'fieldname' => 'PaymentType',
                    'fieldvalue' => 'cenpos',
                ],
                [
                    'level' => 'SFOEOrderTotLoadV4',
                    'lineno' => 0,
                    'seqno' => 0,
                    'fieldname' => 'Token',
                    'fieldvalue' => $order['card_token'],
                ],
                [
                    'level' => 'SFOEOrderTotLoadV4',
                    'lineno' => 0,
                    'seqno' => 0,
                    'fieldname' => 'AuthNumber',
                    'fieldvalue' => 'PDKWA9ZC',
                ],
                [
                    'level' => 'SFOEOrderTotLoadV4',
                    'lineno' => 0,
                    'seqno' => 0,
                    'fieldname' => 'ReferenceNumber',
                    'fieldvalue' => 'PDKWA9ZC',
                ],
            ];
        }

        $payload = [
            'companyNumber' => $this->companyNumber,
            'operatorInit' => $this->operatorInit,
            'tInputccdata' => [
                't-Inputccdata' => [],
            ],

            'tInputheaderdata' => [
                't-inputheaderdata' => [
                    [
                        'taxamount' => 0,
                        'authorizationamount' => 0,
                        'customerid' => '0000' . $customer_number,
                        'ordersource' => 'WEB',
                        'carriercode' => $order['shipping_method'],
                        'shiptoaddr1' => $order['ship_to_address1'],
                        'shiptoaddr2' => $order['ship_to_address2'],
                        'shiptoaddr3' => $order['ship_to_address3'],
                        'shiptocontact' => $order['ship_to_name'],
                        'shiptocity' => $order['ship_to_city'],
                        'shiptocountry' => $order['ship_to_country_code'],
                        'shiptoname' => $order['ship_to_name'],
                        'shiptonumber' => '',
                        'shiptostate' => $order['ship_to_state'],
                        'shiptophone' => $order['ship_to_phone'],
                        'shiptophoneext' => '',
                        'shiptozip' => $order['ship_to_zip_code'],
                        'webtransactiontype' => 'LSF',
                        'ordertype' => $order['order_type'],
                        'ponumber' => $order['po_number'],
                        'revieworderhold' => $review_order_hold,
                    ],
                ],
            ],

            'tInputheaderextradata' => [
                't-inputheaderextradata' => [
                    [
                        'fieldname' => 'placedby',
                        'fieldvalue' => $this->operatorInit,
                    ],
                    [
                        'fieldname' => 'email',
                        'fieldvalue' => $order['customer_email'],
                    ],
                    [
                        'fieldname' => 'contactid',
                        'fieldvalue' => $contact_code,
                    ],
                    [
                        'fieldname' => 'origincd',
                        'fieldvalue' => 'Web',
                    ],
                ],
            ],

            'tInputlinedata' => [
                't-inputlinedata' => $orderLine,
            ],

            'tInputlineextradata' => [
                't-inputlineextradata' => [],
            ],

            'tInfieldvalue' => [
                't-infieldvalue' => $tinfieldvalue,
            ],
        ];

        if (!empty($order['freight_account_number']) && $order['freight_terms_type'] == "C") {
            $payload['tInputheaderextradata']['t-inputheaderextradata'][] = [
                'fieldname' => 'frtbillacct',
                'fieldvalue' => $order['freight_account_number'],
            ];
        }

        if (!empty($order['freight_terms_type']) && $order['freight_terms_type'] !== 'CPU') {
            $payload['tInputheaderextradata']['t-inputheaderextradata'][] = [
                'fieldname' => 'frtterms',
                'fieldvalue' => $order['freight_terms_type'],
            ];
        }

        if ($order['freight_terms_type'] === 'CPU') {
            $payload['tInputheaderextradata']['t-inputheaderextradata'][] = [
                'fieldname' => 'pickuporder',
                'fieldvalue' => 'yes',
            ];
        }

        $response = $this->post('/sxapisfoeordertotloadv4', $payload);

        return $this->adapter->createOrder($response);
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderList(array $filters = []): OrderCollection
    {
        try {

            $fromEntryDate = $filters['start_date'] ?? null;
            $toEntryDate = $filters['end_date'] ?? null;
            $customer_number = $this->customerId($filters);

            // Handle types array as comma-separated string
            $transaction_types = $filters['transaction_types'] ?? [];
            if (!empty($transaction_types) && is_array($transaction_types)) {
                $transaction_types = implode(',', $transaction_types);
            } else {
                $transaction_types = '';
            }

            // Handle statuses for startStage and endStage
            $statuses = $filters['statuses'] ?? [];
            $startStage = $endStage = ''; // default value
            if (!empty($statuses)) {
                $statuses = array_values($statuses); // ensure numeric keys
                if (count($statuses) === 1) {
                    $startStage = $endStage = $statuses[0];
                } else {
                    $startStage = $statuses[0];
                    $endStage = $statuses[count($statuses) - 1];
                }
            }

            $holdOnlyFlg = $filters['hold_only_flag'] ?? false;

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $customer_number,
                'transactionTypes' => $transaction_types,
                'startEnterDate' => $fromEntryDate,
                'endEnterDate' => $toEntryDate,
                'startStage' => $startStage,
                'endStage' => $endStage,
                'orderNumber' => $filters['order_number'] ?? '',
                'customerPurchaseOrder' => $filters['po_number'] ?? '',
                'holdOnlyFlg' => (bool)$holdOnlyFlg,
            ];

            $response = $this->post('/sxapioegetlistofordersv5', $payload);

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
            $customer_number = $this->customerId($orderInfo);
            $order_suffix = $orderInfo['order_suffix'] ?? 'O';

            $payload = [
                'companyNumber' => $this->companyNumber,
                'customerNumber' => $customer_number,
                'operatorInit' => $this->operatorInit,
                'operatorPassword' => '',
                'orderNumber' => $order_number,
                'orderSuffix' => $order_suffix,
                'getOrderInfo' => 'Y',
                'lineSort' => 'A',
                'includeHeaderData' => true,
                'includeTotalData' => true,
                'includeTaxData' => true,
                'includeLineData' => true,
            ];

            $response = $this->post('/sxapioegetsingleorderv3', $payload);

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
            $items = $orderInfo['items'] ?? [];
            $orderLine = array_map(function ($item) {
                return [
                    'itemnumber' => $item['product_code'],
                    'orderqty' => $item['quantity'],
                    'unitofmeasure' => $item['uom'],
                    'warehouseid' => $item['product_warehouse_code'],
                    'itemdesc1' => $item['ItemComment'] ?? $item['product_name'] ?? '',
                    'shipinstrty' => $item['quantity'],
                ];
            }, $items);

            $warehouseId = ! empty($orderLine[0]['warehouseid']) ? $orderLine[0]['warehouseid'] : null;

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'tInputccdata' => [
                    't-Inputccdata' => [],
                ],
                'tInputheaderdata' => [
                    't-inputheaderdata' => [
                        [
                            'taxamount' => 0,
                            'authorizationamount' => 0,
                            'customerid' => '0000' . $customer_number,
                            'ordersource' => 'WEB',
                            'carriercode' => '',
                            'shiptoaddr1' => $orderInfo['ship_to_address1'],
                            'shiptoaddr2' => $orderInfo['ship_to_address2'],
                            'shiptoaddr3' => $orderInfo['ship_to_address3'],
                            'shiptocontact' => $orderInfo['shipping_name'],
                            'shiptocity' => $orderInfo['ship_to_city'],
                            'shiptocountry' => $orderInfo['ship_to_country_code'],
                            'shiptoname' => $orderInfo['shipping_name'],
                            'shiptonumber' => $orderInfo['ship_to_number'],
                            'shiptostate' => $orderInfo['ship_to_state'],
                            'shiptophone' => $orderInfo['phone_number'],
                            'shiptophoneext' => '',
                            'shiptozip' => $orderInfo['ship_to_zip_code'],
                            'webtransactiontype' => 'TSF',
                        ],
                    ],
                ],
                'tInputlinedata' => [
                    't-inputlinedata' => $orderLine,
                ],
            ];

            if (in_array(config('amplify.basic.client_code'), ['DKL', 'NUX'])) {
                $payload['tInputheaderdata']['t-inputheaderdata'][0]['warehouseid'] = $warehouseId;
            }

            $response = $this->post('/sxapisfoeordertotloadv4', $payload);

            $wireTrasnsferFee = 0.00;

            if (!empty($response['tOrdtotextamt']['t-ordtotextamt'])) {
                foreach ($response['tOrdtotextamt']['t-ordtotextamt'] as $tax) {
                    // Wire Transfer addon
                    if (
                        !empty($tax['type']) &&
                        !empty($tax['descrip']) &&
                        strtolower($tax['type']) === 'addon' &&
                        trim(strtolower($tax['descrip'])) === 'wire transfer'
                    ) {
                        $wireTrasnsferFee = $tax['amount'];
                    }
                }
            }

            // Get total order amount from tOrdtotdata
            $salesTaxAmount = $response['tOrdtotdata']['t-ordtotdata'][0]['tottaxamt'] ?? 0.00;
            $totalOrderValue = $response['tOrdtotdata']['t-ordtotdata'][0]['totordamt'] ?? 0.00;
            $totalLineAmount = $response['tOrdtotdata']['t-ordtotdata'][0]['totlineamt'] ?? 0.00;
            $orderLines = $response['tOrdloadlinedata']['t-ordloadlinedata'] ?? [];

            if (config('amplify.erp.use_amplify_shipping')) {

                if (config('amplify.client_code') === 'STV') {
                    $this->setShippingInfo([
                        'country_code' => $orderInfo['ship_to_country_code'],
                        'ship_via' => $orderInfo['shipping_method'],
                        'default_warehouse' => $orderInfo['customer_default_warehouse'],
                    ]);
                }

                $responseBackEnd = $this->getOrderTotalUsingBackend($orderInfo);

                $freightAmount = $responseBackEnd['Order'][0]['FreightAmount'] ?? '0.00';
                $freightRate = $responseBackEnd['Order'][0]['FreightRate'] ?? [];
            }

            $mergedResponse = [
                'Order' => [
                    [
                        'OrderNumber' => '',
                        'TotalLineAmount' => $totalLineAmount,
                        'TotalOrderValue' => $totalOrderValue,
                        'SalesTaxAmount' => $salesTaxAmount,
                        'WireTrasnsferFee' => $wireTrasnsferFee,
                        'FreightAmount' => $freightAmount,
                        'FreightRate' => $freightRate,
                        'OrderLines' => $orderLines,
                        'HazMatCharge' => 0
                    ],
                ],
            ];

            return $this->adapter->getOrderTotal($mergedResponse);


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
            $order = $orderInfo['order'] ?? [];
            $items = $orderInfo['items'] ?? [];
            $customer_number = $this->customerId($orderInfo);

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

            $response = $this->post('/sxapioefullordermntv6', $payload);

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
            $fromEntryDate = $filters['start_date'] ?? null;
            $toEntryDate = $filters['end_date'] ?? null;
            $customer_number = $this->customerId($filters);
            $transaction_types = $filters['transaction_types'] ?? null;

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $customer_number,
                'transactionTypes' => $transaction_types,
                'startEnterDate' => $fromEntryDate,
                'endEnterDate' => $toEntryDate,
            ];

            $response = $this->post('/sxapioegetlistofordersv5', $payload);

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
            $customer_number = $this->customerId($orderInfo);
            $quote_suffix = $orderInfo['quote_suffix'] ?? 'O';

            $payload = [
                'companyNumber' => $this->companyNumber,
                'customerNumber' => $customer_number,
                'operatorInit' => $this->operatorInit,
                'operatorPassword' => '',
                'orderNumber' => $quote_number,
                'orderSuffix' => $quote_suffix,
                'getOrderInfo' => 'Y',
                'lineSort' => 'A',
                'includeHeaderData' => true,
                'includeTotalData' => true,
                'includeTaxData' => true,
                'includeLineData' => true,
            ];

            $response = $this->post('/sxapioegetsingleorderv3', $payload);

            return $this->adapter->getQuotationDetail($response);

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
            $customer_number = $this->customerId($filters);

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $customer_number,
            ];

            $response = $this->post('/sxapisfcustomersummary', $payload);

            return $this->adapter->getCustomerARSummary($response);

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
            $customer_number = $this->customerId($filters);
            $invoice_status = $filters['invoice_status'] ?? 'All';
            $to_entry_date = $filters['to_entry_date'] ?? null;
            $from_entry_date = $filters['from_entry_date'] ?? null;
            $invoice_number = $filters['invoice_number'] ?? 0;
            $record_limit = $filters['limit'] ?? 0;

            if ($invoice_status == 'PAST') {
                $invoice_status = 'CLOSED';
            }

            // Default true
            $includePeriods = [
                'includePeriod1' => true,
                'includePeriod2' => true,
                'includePeriod3' => true,
                'includePeriod4' => true,
                'includePeriod5' => true,
                'includeFutureInvoices' => true,
            ];

            // If CLOSED, override to false
            if ($invoice_status === 'CLOSED' || $invoice_status === 'All') {
                $includePeriods = array_map(fn() => false, $includePeriods);
            }

            $payload = array_merge([
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'operatorPassword' => '',
                'customerNumber' => $customer_number,
                'shipTo' => '',
                'includeInvoices' => true,
                'includeServiceCharges' => true,
                'includeCOD' => true,
                'includeDebitMemos' => true,
                'includeCreditMemos' => true,
                'includeUnappliedCash' => true,
                'includeMiscCredits' => true,
                'includeRebates' => true,
                'includeChecks' => true,
                'includeScheduledPayments' => true,
                'cStatus' => $invoice_status,
                'startDate' => $from_entry_date,
                'endDate' => $to_entry_date,
                'invoiceNumber' => $invoice_number,
                'checkNumber' => 0,
                'recordLimit' => $record_limit,
            ], $includePeriods);

            $response = $this->post('/sxapiargetinvoicelistv3', $payload);

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
     * This API is to get customer AR Open Invoice data from the CSD ERP.
     */
    public function getInvoiceDetail(array $filters = []): Invoice
    {
        try {
            $order_number = $filters['invoice_number'] ?? null;
            $customer_number = $this->customerId($filters);
            $suffix = $filters['suffix'] ?? 'O';

            $payload = [
                'companyNumber' => $this->companyNumber,
                'customerNumber' => $customer_number,
                'operatorInit' => $this->operatorInit,
                'operatorPassword' => '',
                'orderNumber' => $order_number,
                'orderSuffix' => $suffix,
                'getOrderInfo' => 'Y',
                'lineSort' => 'A',
                'includeHeaderData' => true,
                'includeTotalData' => true,
                'includeTaxData' => true,
                'includeLineData' => true,
            ];

            $response = $this->post('/sxapioegetsingleorderv3', $payload);

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
            $customer_number = $this->customerId($paymentInfo);

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

            $response = $this->post('/sxapisanotechange', $payload);

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
            $inputs['limit'] = 1;

            $customerData = $this->getCustomerList($inputs);

            if ($customerData->isEmpty()) {
                return $this->adapter->contactValidation([]);
            }

            /**
             * @var $customer Customer
             */
            $customer = $customerData->first();

            $customerArray = $customer->toArray();

            $customerArray['ValidCombination'] = 'Y';

            return $this->adapter->contactValidation($customerArray);

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

            $customer_number = $this->customerId($inputs);
            $document_number = $inputs['document_number'] ?? null;
            $document_type = $inputs['document_type'] ?? 'I';

            $query = [
                'content' => [
                    'Customer' => $customer_number,
                    'DocNum' => $document_number,
                    'DocType' => $document_type,
                ],
            ];

            $url = 'pdf_document.php';

            $response = $this->post("/{$url}", $query);

            return $this->adapter->getDocument($response);

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

            $customer_number = $this->customerId($filters);

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $customer_number,
                'fromMonth' => $filters['start_month'] ?? '',
                'toMonth' => $filters['end_month'] ?? '',
                'fromYear' => $filters['start_year'] ?? '',
                'toYear' => $filters['end_year'] ?? '',
            ];

            $response = $this->post('/sxapisfgetoeorderhistory', $payload);

            return $this->adapter->getPastItemList($response);

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
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'orderNumber' => $inputs['order_number'] ?? null,
                'orderSuffix' => $inputs['order_suffix'] ?? 0,
            ];

            $response = $this->post('/sxapisfgettrackingnum', $payload);

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

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $this->customerId($filters),
                'requestType' => 'credit',
            ];

            $response = $this->post('/sxapiargetcustomerdata', $payload);

            return $this->adapter->getTermsType($response);

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
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'tMntTt' => ['t-mnt-tt' => $tMnTt],
            ];

            $response = $this->post('/sxapicamcontactmnt', $payload);

            $contact_code = preg_replace('/Set#: (\d*) Update Successful, Cono: 1 Contact ID: ([\d+])/', '$2', trim($response['returnData']));

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

            $customer_number = $this->customerId($filters);

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'subjectRoleType' => 'arsc',
                'subjectPrimaryKey' => (string)$customer_number,
            ];

            if (!empty($filters['name'])) {
                $nameParts = split_full_name($filters['name'] ?? '');
                $payload['firstName'] = strtoupper($nameParts['first']);
                $payload['lastName'] = strtoupper($nameParts['last']);
            }

            if (!empty($filters['limit'])) {
                $payload['recordLimit'] = $filters['limit'];
            }

            if (!empty($filters['contact_code'])) {
                $payload['contactID'] = $filters['contact_code'];
            }

            $response = $this->post('/sxapicamgetcontactlistv4', $payload);

            return $this->adapter->getContactList($response);

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
            $customer_number = $this->customerId($filters);

            $contact_code = $filters['contact_code'] ?? null;

            if ($customer_number == null) {
                throw new CsdErpException('Contact Code is missing.');
            }

            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'contactID' => $contact_code,
                'subjectRoleType' => 'arsc',
                'subjectPrimaryKey' => (string)$customer_number,
            ];

            $response = $this->post('/sxapicamgetcontactlistv4', $payload);

            return $this->adapter->getContactDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getContactDetail();
        }
    }


    /**
     * Acts as a query builder to fetch specific fields from the CSD ARSC table.
     *
     * This API fetches customer-level configuration such as freight terms code and row pointer
     * from the CSD using the FetchWhere service.
     *
     * @return array Contains 'frttermscd' and 'rowpointer' if found; empty array otherwise.
     *
     * @throws Exception
     */
    public function getFreightDetails(array $inputs = []): array
    {
        try {
            $customerNumber = $this->customerId($inputs);
            $cacheKey = "freight_details_customer_{$customerNumber}";

            return Cache::rememberForever($cacheKey, function () use ($customerNumber) {
                // First call to arsc
                $firstPayload = [
                    'CompanyNumber' => $this->companyNumber,
                    'Operator' => $this->operatorInit,
                    'TableName' => 'arsc',
                    'WhereClause' => "arsc.cono = 1 and arsc.custno = $customerNumber",
                    'BatchSize' => 1,
                    'RestartRowID' => '',
                ];

                //Acts as a query builder to fetch specific fields from the CSD ARSC table.
                $arscResponse = $this->post('/proxy/FetchWhere', $firstPayload);
                $arscData = $arscResponse['ttblarsc'][0] ?? [];

                $frttermscd = strtoupper($arscData['frttermscd'] ?? '');
                $rowpointer = $arscData['rowpointer'] ?? null;

                // Default single result (uppercase applied)
                $results = [[
                    'frttermscd' => $frttermscd,
                    'accountnumber' => null,
                    'carrierid' => null,
                ]];

                // rowpointer exists, do second call
                if ($rowpointer) {
                    $secondPayload = [
                        'CompanyNumber' => $this->companyNumber,
                        'Operator' => $this->operatorInit,
                        'TableName' => 'sastf',
                        'WhereClause' => "sastf.cono = 1 and sastf.srcrowpointer = '$rowpointer' and sastf.billlevelcd = 'c'",
                        'BatchSize' => 50,
                        'RestartRowID' => '',
                    ];
                    //Acts as a query builder to fetch specific fields from the CSD ARSC table.
                    $sastfResponse = $this->post('/proxy/FetchWhere', $secondPayload);
                    $sastfDataList = $sastfResponse['ttblsastf'] ?? [];

                    // Only override if second call returns results
                    if (!empty($sastfDataList)) {
                        $results = array_map(function ($item) use ($frttermscd) {
                            return [
                                'frttermscd' => $frttermscd,
                                'accountnumber' => $item['billaccount'] ?? null,
                                'carrierid' => strtoupper($item['carrierid'] ?? ''),
                            ];
                        }, $sastfDataList);
                    }
                }

                return $results;
            });
        } catch (Exception $e) {
            $this->exceptionHandler($e);

            return [[
                'frttermscd' => null,
                'accountnumber' => null,
                'carrierid' => null,
            ]];
        }
    }

    public function getNotesList(array $inputs = []): OrderNoteCollection
    {
        try {
            $primary_key = $inputs['order_number'] ?? null;
            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'notesType' => 'o',
                'primaryKey' => $primary_key,
                'secondaryKey' => '',
                'requiredNotesOnlyFlag' => false,
                'lineFeedFlag' => true,
                'recordLimit' => 0,
            ];

            $response = $this->post('/sxapisagetnoteslist', $payload);

            return $this->adapter->getNotesList($response);

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getNotesList();
        }
    }

    public function getPODetails($inputs = []): array
    {
        try {
            $poNumber = str_replace('PO#', '', $inputs['poNumber']);
            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'purchaseOrderNumber' => $poNumber,
                'purchaseOrderSuffix' => 0,
                'lineSort' => 'A',
                'includeHeaderData' => false,
                'includeTotalData' => false,
                'includeLineData' => true,
            ];

            return $this->post('/sxapipogetsinglepurchaseorderv2', $payload);

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return [];
        }
    }

    public function getInvoiceTransaction(array $inputs = []): InvoiceTransactionCollection
    {
        try {
            $payload = [
                'companyNumber' => $this->companyNumber,
                'operatorInit' => $this->operatorInit,
                'customerNumber' => $this->customerId($inputs),
                'invoiceType' => '',
                'invoiceNumber' => $inputs['invoice_number'] ?? null,
                'invoiceSuffix' => (string)$inputs['suffix'] ?? '0',
                'transactionType' => $inputs['transaction_type'] ?? 'O',
            ];

            $response = $this->post('/sxapiSFGetInvoiceDetail', $payload);

            return $this->adapter->getInvoiceTransaction($response);

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getInvoiceTransaction();
        }
    }

    /**
     * @throws CsdErpException
     */
    public function createUpdateCustomerPartNumber(array $inputs = []): array
    {
        $customer_number = $this->customerId($inputs);

        $payload = [
            'companyNumber' => $this->companyNumber,
            'operatorInit' => $this->operatorInit,
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

    protected function getIdmBaseUrl(): string
    {
        // Remove SX path and point to IDM
        return str_replace(
            'SX/web/sxapirestservice',
            'IDM',
            $this->config['url']
        );
    }

    /**
     * Get printable document (Invoice / Order / Quote) from IDM
     *
     * @throws CsdErpException
     */
    public function getPrintableDocument(array $inputs = []): DocumentCollection
    {
        try {
            // Extract parameters from array with defaults
            $type = $inputs['type'] ?? null;
            $orderNumber = $inputs['order_number'] ?? null;
            $suffix = $inputs['suffix'] ?? null;
            $offset = $inputs['offset'] ?? 0;
            $limit = $inputs['limit'] ?? 11;

            if (!$type || !$orderNumber) {
                throw new \InvalidArgumentException('Type and order number are required.');
            }

            // Build query conditions
            $conditions = [
                sprintf('@Order_Number = "%s"', $orderNumber),
            ];

            if ($suffix !== null && $suffix !== '') {
                $conditions[] = sprintf('@Order_Suffix = "%s"', $suffix);
            }

            $conditionString = implode(' AND ', $conditions);

            // Build query
            if ($type === 'order') {
                $docTypes = ['Acknowledgement', 'Invoice', 'Pick_List'];

                $unionParts = array_map(
                    fn ($t) => sprintf('/%s[%s]', $t, $conditionString),
                    $docTypes
                );

                $query = implode(' UNION ', $unionParts) . ' SORTBY(@LASTCHANGEDTS DESCENDING)';
            } else {
                $query = sprintf(
                    '/%s[%s] SORTBY(@LASTCHANGEDTS DESCENDING)',
                    ucfirst($type),
                    $conditionString
                );
            }

            // Make HTTP request
            $response = Http::csdErp()
                ->baseUrl($this->getIdmBaseUrl())
                ->get('/api/items/search', [
                    '$query'        => $query,
                    '$offset'       => $offset,
                    '$limit'        => $limit,
                    '$includeCount' => 'true',
                    '$state'        => 0,
                ])
                ->json();

            // Parse response using adapter
            return $this->adapter->renderPrintableDocument($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);
            return new DocumentCollection([]);
        }
    }




}
