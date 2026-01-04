<?php

namespace Amplify\ErpApi\Adapters;

use Amplify\ErpApi\Wrappers\OrderPODetails;
use Amplify\ErpApi\Wrappers\ShippingLocationValidation;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Amplify\ErpApi\Facades\ErpApi;
use Amplify\ErpApi\Wrappers\Order;
use Amplify\ErpApi\Wrappers\Contact;
use Amplify\ErpApi\Wrappers\Invoice;
use Amplify\ErpApi\Wrappers\Campaign;
use Amplify\ErpApi\Wrappers\Customer;
use Amplify\ErpApi\Wrappers\Document;
use Amplify\ErpApi\Wrappers\PastItem;
use Amplify\ErpApi\Wrappers\Cylinders;
use Amplify\ErpApi\Wrappers\OrderNote;
use Amplify\ErpApi\Wrappers\Quotation;
use Amplify\ErpApi\Wrappers\TermsType;
use Amplify\ErpApi\Wrappers\Warehouse;
use Amplify\ErpApi\Wrappers\CustomerAR;
use Amplify\ErpApi\Wrappers\OrderTotal;
use Amplify\ErpApi\Wrappers\OrderDetail;
use Amplify\ErpApi\Wrappers\ProductSync;
use Amplify\ErpApi\Wrappers\CreatePayment;
use Amplify\ErpApi\Wrappers\TrackShipment;
use Amplify\ErpApi\Wrappers\CampaignDetail;
use Amplify\ErpApi\Wrappers\ShippingOption;
use Amplify\ErpApi\Wrappers\CreateQuotation;
use Amplify\ErpApi\Wrappers\ShippingLocation;
use Amplify\ErpApi\Interfaces\ErpApiInterface;
use Amplify\ErpApi\Wrappers\ContactValidation;
use Amplify\ErpApi\Collections\OrderCollection;
use Amplify\ErpApi\Wrappers\CreateOrUpdateNote;
use Amplify\ErpApi\Wrappers\InvoiceTransaction;
use Amplify\ErpApi\Exceptions\InvalidBase64Data;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\CylinderCollection;
use Amplify\ErpApi\Collections\PastItemCollection;
use Amplify\ErpApi\Collections\OrderNoteCollection;
use Amplify\ErpApi\Collections\QuotationCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Collections\OrderDetailCollection;
use Amplify\ErpApi\Collections\ProductSyncCollection;
use Amplify\ErpApi\Wrappers\ProductPriceAvailability;
use Amplify\ErpApi\Collections\TrackShipmentCollection;
use Amplify\ErpApi\Collections\CampaignDetailCollection;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\CreateQuotationCollection;
use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Collections\InvoiceTransactionCollection;
use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CsdErpAdapter implements ErpApiInterface
{
    use \Amplify\ErpApi\Traits\ManageDocumentTrait;

    /*
    |--------------------------------------------------------------------------
    | UTILITY FUNCTIONS
    |--------------------------------------------------------------------------
    */

    private function mapFieldAttributes(array $fields = []): array
    {
        $values = [];

        if (!empty($fields)) {
            foreach ($fields as $field) {
                if (isset($field['fieldName']) && isset($field['fieldValue'])) {
                    $values[$field['fieldName']] = trim($field['fieldValue']);
                } elseif (isset($field['fieldname']) && isset($field['fieldvalue'])) {
                    $values[$field['fieldname']] = trim($field['fieldvalue']);
                }
            }
        }

        return $values;
    }

    /**
     * Extract In-House Delivery Dates from tFieldlist, keyed by seqNo
     *
     * @param array $orderInfo
     * @return array [seqNo => inHouseDeliveryDate]
     */
    private function extractLineLevelFieldMap(array $orderInfo): array
    {
        $map = [];

        foreach ($orderInfo['tFieldlist']['t-fieldlist'] ?? [] as $field) {
            $fieldName = $field['fieldName'] ?? null;
            $seqNo = $field['seqNo'] ?? null;
            $fieldValue = $field['fieldValue'] ?? null;

            // Only process fields with seqNo
            if ($seqNo === null || $fieldName === null) {
                continue;
            }

            // only interested in linelevel- prefixed fields
            if (str_starts_with($fieldName, 'linelevel-')) {
                // Initialize sequence entry if not present
                if (!isset($map[$seqNo])) {
                    $map[$seqNo] = [];
                }

                // Strip prefix "linelevel-" and store field
                $cleanName = str_replace('linelevel-', '', $fieldName);
                $map[$seqNo][$cleanName] = $fieldValue;
            }
        }

        return $map;
    }

    /**
     * Extract extra charges from attributes.
     *
     * @param array $attributes
     * @return array
     */
    protected function renderExtraCharges(array $attributes): array
    {
        return collect($attributes)
            ->filter(fn($value, $key) => str_starts_with($key, 'addondesc') && !empty($value))
            ->mapWithKeys(function ($desc, $descKey) use ($attributes) {
                // extract index number from key
                $index = str_replace('addondesc', '', $descKey);
                $netKey = 'addonnet' . $index;

                return [$desc => $attributes[$netKey] ?? 0];
            })
            ->toArray();
    }

    /**
     * This function will check if the ERP can be enabled
     */
    public function enabled(): bool
    {
        return true;
    }

    /**
     * This function will check if the ERP has Multiple warehouse capabilities
     */
    public function allowMultiWarehouse(): bool
    {
        return true;
    }

    /**
     * This function will return the ERP Carrier code options
     */
    public function getShippingOption(array $data = []): ShippingOptionCollection
    {
        $collection = new ShippingOptionCollection;

        foreach ($data as $datum) {
            $model = new ShippingOption($datum);
            $model->InternalId = $datum['id'];
            $model->CarrierCode = $datum['code'];
            $model->Name = $datum['name'];
            $model->CarrierDescription = $datum['description'];
            $model->Driver = $datum['driver'];

            $setting = json_decode($datum['setting'], true);
            $model->Value = isset($setting[0]['value']) ? strtolower($setting[0]['value']) : null;

            $collection->push($model);
        }

        return $collection;
    }

    /**
     * This API is to get customer ship to locations entity information from the FACTS ERP
     */
    public function validateCustomerShippingLocation(array $location = []): ShippingLocationValidation
    {
        $error = $location['error'] ?? null;
        $name = $location['name'] ?? null;
        $code = $location['code'] ?? null;

        $location = $location['tOutAddrValidation']['t-out-addr-validation'] ?? [];

        $attributes = $location[0] ?? [];

        $model = new ShippingLocationValidation([...$attributes, 'name' => $name, 'code' => $code]);

        $model->Name = $name;
        $model->Reference = $code;
        $model->Message = isset($location['cErrorMessage']) ? trim($location['cErrorMessage']) : null;
        $model->Response = !isset($location['cErrorMessage']) ? 'Success' : 'Failed';

        if (!empty($attributes)) {
            $model->Address1 = $attributes['streetaddr'] ?? null;
            $model->Address2 = $attributes['streetaddr2'] ?? null;
            $model->Address3 = $attributes['streetaddr3'] ?? null;
            $model->CountryCode = $attributes['country'] ?? null;
            $model->City = $attributes['city'] ?? null;
            $model->State = $attributes['state'] ?? null;
            $model->ZipCode = $attributes['zipcd'] ?? null;
            $model->Status = $attributes['addressoverfl'] ?? false;
            $model->Details = null;
        }

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a new cash customer account
     */
    public function createCustomer(array $attributes = []): Customer
    {
        $customer = !empty($attributes['Customers']) ? array_shift($attributes['Customers']) : [];

        return $this->renderSingleCustomer($customer);
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     */
    public function getCustomerList(array $customers = []): CustomerCollection
    {
        $customerList = new CustomerCollection;

        if (!empty($customers['tCustLstV3'])) {
            foreach (($customers['tCustLstV3']['t-custLstV3'] ?? []) as $customer) {
                $outFields = array_filter(
                    $customers['tOutfieldvalue']['t-outfieldvalue'],
                    fn($item) => $item['level'] == (string)intval($customer['custNo']));

                $outFields = $this->mapFieldAttributes($outFields);

                $attributes = array_merge($customer, $outFields);

                $model = new Customer($attributes);

                if (!empty($attributes)) {
                    $model->CustomerNumber = intval($attributes['custNo'] ?? null);
                    $model->ArCustomerNumber = intval($attributes['custNo'] ?? null);
                    $model->CustomerName = $attributes['name'] ?? null;
                    $model->CustomerAddress1 = $attributes['addr1'] ?? null;
                    $model->CustomerAddress2 = $attributes['addr2'] ?? null;
                    $model->CustomerAddress3 = $attributes['addr3'] ?? null;
                    $model->CustomerCity = $attributes['city'] ?? null;
                    $model->CustomerState = $attributes['state'] ?? null;
                    $model->CustomerZipCode = $attributes['zipCd'] ?? null;
                    $model->CustomerCountry = $attributes['currencyty'] ? strtoupper($attributes['currencyty']) : null;
                    $model->DefaultShipTo = $attributes['shipTo'] ?? null;
                }

                $customerList->push($model);
            }
        }

        return $customerList;
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     */
    public function getCustomerDetail(array $customer = []): Customer
    {
        return $this->renderSingleCustomer($customer);
    }

    /**
     * This API is to get customer ship to locations entity information from the FACTS ERP
     */
    public function getCustomerShippingLocationList(array $locations = []): ShippingLocationCollection
    {
        $customerShippingLocations = new ShippingLocationCollection;

        if (!empty($locations)) {
            $locationFields = $locations['tShiptovaluepair']['t-shiptovaluepair'] ?? [];

            foreach (($locations['tShiptolstv3']['t-shiptolstv3'] ?? []) as $location) {
                $supportFields = array_filter($locationFields, fn($item) => $location['shipto'] == $item['shipto']);
                $supportFields = $this->mapFieldAttributes($supportFields);
                $location = array_merge($location, $supportFields);
                $customerShippingLocations->push($this->renderSingleCustomerShippingLocation($location));
            }
        }

        return $customerShippingLocations;
    }

    /**
     * This API is to get item details with pricing and availability for the given warehouse location ID
     */
    public function getProductPriceAvailability(array $filters = []): ProductPriceAvailabilityCollection
    {
        $collection = new ProductPriceAvailabilityCollection;

        $tOemultprcoutV3 = collect($filters['tOemultprcoutV3']['t-oemultprcoutV3'] ?? [])->groupBy('seqno')->toArray();
        $tOemultprcoutbrk = collect($filters['tOemultprcoutbrk']['t-oemultprcoutbrk'] ?? [])->groupBy('seqno')->toArray();
        $tOutfieldValue = $filters['tOutfieldvalue']['t-outfieldvalue'] ?? [];

        foreach ($tOutfieldValue as $index => $value) {
            if (preg_match('/(\d{6})\|(.+)\|(.+)/', $value['level']) !== 0) {
                $tOutfieldValue[$index]['seqno'] = intval($value['level']);
            }
        }

        $tOutfieldValue = collect($tOutfieldValue)->groupBy('seqno')->toArray();

        $items = [];

        foreach ($tOemultprcoutV3 as $seqno => $entry) {
            $items[$seqno] = array_merge(
                array_shift($entry),
                empty($tOemultprcoutbrk[$seqno]) ? [] : array_shift($tOemultprcoutbrk[$seqno]),
                empty($tOutfieldValue[$seqno]) ? [] : $this->mapFieldAttributes($tOutfieldValue[$seqno])
            );
        }

        foreach ($items as $item) {
            $collection->push($this->renderSingleProductPriceAvailability($item));
        }

        return $collection;
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getProductSync(array $filters = []): ProductSyncCollection
    {
        $collection = new ProductSyncCollection;

        if (!empty($filters)) {
            $filters = $filters['tItemmasterv3']['t-itemmasterv3'] ?? [];
            foreach (($filters ?? []) as $item) {
                $collection->push($this->renderProductSync($item));
            }
        }

        return $collection;
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getWarehouses(array $filters = []): WarehouseCollection
    {
        $warehouseCollection = new WarehouseCollection;

        foreach ($filters as $warehouse) {
            $warehouseCollection->push($this->renderSingleWarehouse($warehouse));
        }

        return $warehouseCollection;
    }

    /**
     * This API is to create an order in the FACTS ERP
     */
    public function createOrder(array $orderInfo = []): Order
    {
        $model = new Order($orderInfo);
        $erpData = $orderInfo ?? [];
        if (isset($erpData['error'])) {
            // Handle error scenario if needed
            $model->Message = $erpData['error'];
            return $model;
        }

        $header = $erpData['tOrdloadhdrdata']['t-ordloadhdrdata'][0] ?? [];
        $lines = $erpData['tOrdloadlinedata']['t-ordloadlinedata'] ?? [];
        $totals = $erpData['tOrdtotdata']['t-ordtotdata'][0] ?? [];
        $notesRaw = $erpData['tOrdtotextamt']['t-ordtotextamt'] ?? [];

        $flattened = array_merge(
            $header,
            $totals,
        );


        $model->OrderNumber = $flattened['orderno'] ?? null;
        $model->OrderStatus = 'Accepted';

        return $model;
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderList(array $customerOrders = []): OrderCollection
    {
        $orders = new OrderCollection;
        if (!empty($customerOrders)) {
            foreach (($customerOrders['tOeordV5']['t-oeordV5'] ?? []) as $order) {
                $orders->push($this->renderSingleOrder($order));
            }
        }

        return $orders;
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderDetail(array $orderInfo = []): Order
    {
        $headers = $this->mapFieldAttributes($orderInfo['tFieldlist']['t-fieldlist'] ?? []);

        $taxes = $orderInfo['tOetaxsa']['t-oetaxsa'] ?? [];

        $headers['lineLevelFieldMap'] = $this->extractLineLevelFieldMap($orderInfo);

        foreach ($taxes as $index => $orderTax) {
            foreach (($orderInfo['tOetaxar']['t-oetaxar'] ?? []) as $item) {
                if ($orderTax['taxcode'] == $item['localcode']) {
                    $taxes[$index] = array_merge($orderTax, $item);
                }
            }
        }

        $orderInfo = array_merge($orderInfo, $headers, [
            'ordertaxes' => $taxes,
            'orderlines' => ($orderInfo['tOelineitemV3']['t-oelineitemV3'] ?? []),
        ]);

        unset(
            $orderInfo['tFieldlist'],
            $orderInfo['tOetaxsa'],
            $orderInfo['tOetaxar'],
            $orderInfo['tOelineitemV3']
        );

        array_walk($orderInfo, fn(&$item, $key) => $item = (!is_array($item)) ? trim($item) : $item);

        return $this->renderSingleOrder($orderInfo);
    }

    public function getOrderTotal(array $orderInfo = []): OrderTotal
    {
        $model = new OrderTotal($orderInfo);

        $attributes = isset($orderInfo['Order'])
            ? array_shift($orderInfo['Order'])
            : [];

        $model->OrderNumber = !empty($attributes['OrderNumber']) ? $attributes['OrderNumber'] : null;
        $model->TotalLineAmount = !empty($attributes['TotalLineAmount']) ? (float)filter_var($attributes['TotalLineAmount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
        $model->TotalOrderValue = !empty($attributes['TotalOrderValue']) ? (float)filter_var($attributes['TotalOrderValue'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
        $model->SalesTaxAmount = !empty($attributes['SalesTaxAmount']) ? (float)filter_var($attributes['SalesTaxAmount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
        $model->FreightAmount = !empty($attributes['FreightAmount']) ? (float)filter_var($attributes['FreightAmount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
        $model->FreightRate = !empty($attributes['FreightRate']) ? $attributes['FreightRate'] : [];
        $model->WireTrasnsferFee = !empty($attributes['WireTrasnsferFee']) ? (float)filter_var($attributes['WireTrasnsferFee'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
        $model->HazMatCharge = isset($attributes['HazMatCharge']) && $attributes['HazMatCharge'] ? (float) $attributes['HazMatCharge'] : null;
        $model->OrderLines = new Collection();

        if (!empty($attributes['OrderLines'])) {
            foreach ($attributes['OrderLines'] as $line) {
                $model->OrderLines->push((object)[
                    'ItemNumber' => $line['shipprod'] ?? '',
                    'ItemDesc' => $line['descrip'] ?? '',
                    'Quantity' => $line['qtyord'] ?? 0,
                    'UoM' => $line['unit'] ?? '',
                    'UnitPrice' => $line['price'] ?? 0,
                    'TotalLineAmount' => $line['netamt'] ?? 0,
                ]);
            }
        }

        return $model;
    }

    /**
     * This API is to create a quotation in the FACTS ERP
     */
    public function createQuotation(array $orderInfo = []): CreateQuotationCollection
    {
        $quoteCollection = new CreateQuotationCollection;
        if (!empty($orderInfo)) {
            $customerOrders = $orderInfo['Orders'] ?? $orderInfo['Order'];
            foreach (($customerOrders ?? []) as $quote) {
                $quoteCollection->push($this->renderSingleCreateQuotation($quote));
            }
        }

        return $quoteCollection;
    }

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     */
    public function getQuotationList(array $customerOrders = []): QuotationCollection
    {
        $quotes = new QuotationCollection;

        if (!empty($customerOrders)) {
            foreach (($customerOrders['tOeordV5']['t-oeordV5'] ?? []) as $quote) {
                $quotes->push($this->renderSingleQuotation($quote));
            }
        }

        return $quotes;
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     */
    public function getQuotationDetail(array $orderInfo = []): Quotation
    {
        $headers = $this->mapFieldAttributes($orderInfo['tFieldlist']['t-fieldlist'] ?? []);
        $taxes = $orderInfo['tOetaxsa']['t-oetaxsa'] ?? [];

        foreach ($taxes as $index => $orderTax) {
            foreach (($orderInfo['tOetaxar']['t-oetaxar'] ?? []) as $item) {
                if ($orderTax['taxcode'] == $item['localcode']) {
                    $taxes[$index] = array_merge($orderTax, $item);
                }
            }
        }

        $orderInfo = array_merge($orderInfo, $headers, [
            'ordertaxes' => $taxes,
            'orderlines' => ($orderInfo['tOelineitemV3']['t-oelineitemV3'] ?? []),
        ]);

        unset(
            $orderInfo['tFieldlist'],
            $orderInfo['tOetaxsa'],
            $orderInfo['tOetaxar'],
            $orderInfo['tOelineitemV3']
        );

        array_walk($orderInfo, fn(&$item, $key) => $item = (!is_array($item)) ? trim($item) : $item);

        return $this->renderSingleQuotation($orderInfo);

    }

    /**
     * This API is to get customer Accounts Receivables information from the FACTS ERP
     */
    public function getCustomerARSummary(array $attributes = []): CustomerAR
    {
        $attributes = $attributes['tCustsummary']['t-custsummary'] ?? [];

        $model = new CustomerAR($attributes);

        if (!empty($attributes)) {
            $attributes = array_shift($attributes);

            $model->CustomerNum = $attributes['CustomerNum'] ?? null;

            $model->CustomerName = $attributes['custname'] ?? null;
            $model->Address1 = $attributes['addr1'] ?? null;
            $model->Address2 = $attributes['addr2'] ?? null;
            $model->City = $attributes['city'] ?? null;
            $model->ZipCode = $attributes['zipcd'] ?? null;
            $model->State = $attributes['state'] ?? null;
            $model->AgeDaysPeriod1 = $attributes['ageprd1'] ?? null;
            $model->AgeDaysPeriod2 = $attributes['ageprd2'] ?? null;
            $model->AgeDaysPeriod3 = $attributes['ageprd3'] ?? null;
            $model->AgeDaysPeriod4 = $attributes['ageprd4'] ?? null;
            $model->AmountDue = $attributes['amtdue'] ?? null;
            $model->BillingPeriodAmount = $attributes['billprdamt'] ?? null;
            $model->DateOfFirstSale = $attributes['firstsaledt'] ?? null;
            $model->DateOfLastPayment = $attributes['lastpaydt'] ?? null;
            $model->DateOfLastSale = $attributes['lastsaledt'] ?? null;
            $model->FutureAmount = $attributes['futureamt'] ?? null;
            $model->OpenOrderAmount = $attributes['openordamt'] ?? null;
            $model->SalesLastYearToDate = $attributes['salesLYTD'] ?? null;
            $model->SalesMonthToDate = $attributes['salesMTD'] ?? null;
            $model->SalesYearToDate = $attributes['salesYTD'] ?? null;
            $model->TermsDescription = $attributes['termsdesc'] ?? null;
            $model->TermsCode = $attributes['termsdesc'] ?? null;

            $model->TradeAgePeriod1Amount = $attributes['tradeageprd1'] ?? null;
            $model->TradeAgePeriod2Amount = $attributes['tradeageprd2'] ?? null;
            $model->TradeAgePeriod3Amount = $attributes['tradeageprd3'] ?? null;
            $model->TradeAgePeriod4Amount = $attributes['tradeageprd4'] ?? null;
            $model->TradeAmountDue = $attributes['tradeamtdue'] ?? null;
            $model->TradeBillingPeriodAmount = $attributes['tradebillprdamt'] ?? null;
            $model->AvgDaysToPay1 = $attributes['tradeageprd1'] ?? null;
            $model->AvgDaysToPay1Wgt = $attributes['AvgDaysToPay1Wgt'] ?? null;
            $model->AvgDaysToPay2 = $attributes['tradeageprd2'] ?? null;
            $model->AvgDaysToPay2Wgt = $attributes['AvgDaysToPay2Wgt'] ?? null;
            $model->AvgDaysToPay3 = $attributes['tradeageprd3'] ?? null;
            $model->AvgDaysToPay3Wgt = $attributes['AvgDaysToPay3Wgt'] ?? null;
            $model->AvgDaysToPayDesc1 = $attributes['AvgDaysToPayDesc1'] ?? null;
            $model->AvgDaysToPayDesc2 = $attributes['AvgDaysToPayDesc2'] ?? null;
            $model->AvgDaysToPayDesc3 = $attributes['AvgDaysToPayDesc3'] ?? null;
            $model->CreditCheckType = $attributes['CreditCheckType'] ?? null;
            $model->CreditLimit = $attributes['CreditLimit'] ?? null;
            $model->HighBalance = $attributes['HighBalance'] ?? null;
            $model->LastPayAmount = $attributes['LastPayAmount'] ?? null;
            $model->NumInvPastDue = $attributes['NumInvPastDue'] ?? null;
            $model->NumOpenInv = $attributes['NumOpenInv'] ?? null;
            $model->NumPayments1 = $attributes['NumPayments1'] ?? null;
            $model->NumPayments2 = $attributes['NumPayments2'] ?? null;
            $model->NumPayments3 = $attributes['NumPayments3'] ?? null;

            $model->TradeAgePeriod1Text = $attributes['agedaysper1'] ?? null;
            $model->TradeAgePeriod2Text = $attributes['agedaysper2'] ?? null;
            $model->TradeAgePeriod3Text = $attributes['agedaysper3'] ?? null;
            $model->TradeAgePeriod4Text = $attributes['agedaysper4'] ?? null;

            $model->TradeBillingPeriodText = $attributes['TradeBillingPeriodText'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to get customer Accounts Receivables Open Invoices data from the FACTS ERP
     */
    public function getInvoiceList(array $attributes = []): InvoiceCollection
    {
        $invoiceList = new InvoiceCollection;

        foreach (($attributes['tArtransV3']['t-artransV3'] ?? []) as $invoice) {
            if($invoice['transcdraw'] !== 11 && config('amplify.client_code') === 'STV') {
                continue;
            }
            $invoiceList->push($this->renderSingleInvoice($invoice));
        }

        return $invoiceList;
    }

    /**
     * This API is to get customer AR Open Invoice data from the FACTS ERP.
     */
    public function getInvoiceDetail(array $orderInfo = []): Invoice
    {
        $headers = $this->mapFieldAttributes($orderInfo['tFieldlist']['t-fieldlist'] ?? []);
        $taxes = $orderInfo['tOetaxsa']['t-oetaxsa'] ?? [];
        $headers['lineLevelFieldMap'] = $this->extractLineLevelFieldMap($orderInfo);

        foreach ($taxes as $index => $orderTax) {
            foreach (($orderInfo['tOetaxar']['t-oetaxar'] ?? []) as $item) {
                if ($orderTax['taxcode'] == $item['localcode']) {
                    $taxes[$index] = array_merge($orderTax, $item);
                }
            }
        }

        $orderInfo = array_merge($orderInfo, $headers, [
            'ordertaxes' => $taxes,
            'orderlines' => ($orderInfo['tOelineitemV3']['t-oelineitemV3'] ?? []),
        ]);

        unset(
            $orderInfo['tFieldlist'],
            $orderInfo['tOetaxsa'],
            $orderInfo['tOetaxar'],
            $orderInfo['tOelineitemV3']
        );

        array_walk($orderInfo, fn(&$item, $key) => $item = (!is_array($item)) ? trim($item) : $item);

        return $this->renderSingleInvoice($orderInfo);
    }

    /**
     * This API is to create a AR payment on the customers account.
     */
    public function createPayment(array $paymentInfo = []): CreatePayment
    {
        $model = new CreatePayment($paymentInfo);

        if (!empty($paymentInfo['ArPayment'])) {
            $attributes = $paymentInfo['ArPayment'] ?? [];

            $model->AuthorizationNumber = $attributes['AuthorizationNumber'] ?? null;
            $model->OnAccountDocument = $attributes['OnAccountDocument'] ?? null;
            $model->OnAccountAmount = $attributes['OnAccountAmount'] ?? null;
            $model->Receipt = $attributes['Receipt'] ?? null;
            $model->DistributedAmount = $attributes['DistributedAmount'] ?? null;
            $model->Message = $attributes['Message'] ?? null;
            $model->Token = $attributes['Token'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to create a order note.
     */
    public function createOrUpdateNote(array $noteInfo = []): CreateOrUpdateNote
    {
        $model = new CreateOrUpdateNote($noteInfo);

        if (!empty($noteInfo['UpdateNotes'])) {
            $attributes = $noteInfo['UpdateNotes'] ?? [];

            $model->Status = $attributes['Status'] ?? null;
            $model->NoteNum = $attributes['NoteNum'] ?? null;
        }

        return $model;
    }

    private function renderSingleCustomer(array $attributes): Customer
    {
        $model = new Customer($attributes);

        if (!empty($attributes)) {

            $attributes['tFieldvaluepair'] = $this->mapFieldAttributes($attributes['tFieldvaluepair']['t-fieldvaluepair'] ?? []);

            $model->CustomerNumber = $attributes['customerNumber'] ?? null;
            $model->ArCustomerNumber = $attributes['arCustomerNumber'] ?? null;
            $model->CustomerName = $attributes['customerName'] ?? null;
            $model->CustomerAddress1 = $attributes['customerAddress1'] ?? null;
            $model->CustomerAddress2 = $attributes['customerAddress2'] ?? null;
            $model->CustomerAddress3 = $attributes['customerAddress3'] ?? null;
            $model->CustomerCity = $attributes['customerCity'] ?? null;
            $model->CustomerState = $attributes['customerState'] ?? null;
            $model->CustomerZipCode = $attributes['customerZipCode'] ?? null;
            $model->CustomerCountry = $attributes['customerCountry'] ? strtoupper($attributes['customerCountry']) : null;
            $model->CustomerPhone = $attributes['phone'] ?? null;
            $model->CustomerContact = $attributes['CustomerContact'] ?? null;
            $model->DefaultShipTo = $attributes['defaultShipTo'] ?? null;
            $model->DefaultWarehouse = $attributes['defaultWarehouse'] ?? null;
            $model->CarrierCode = $attributes['carrierCode'] ?? null;
            $model->PriceList = $attributes['priceList'] ?? null;
            $model->BackorderCode = $attributes['BackorderCode'] ?? null;
            $model->CustomerClass = $attributes['customerClass'] ?? null;
            $model->SuspendCode = $attributes['suspendCode'] ?? null;
            $model->AllowArPayments = $attributes['AllowArPayments'] ?? null;
            $model->CreditCardOnly = $attributes['CreditCardOnly'] ?? null;
            $model->FreightOptionAmount = !empty($attributes['FreightOptionAmount']) ? floatval($attributes['FreightOptionAmount']) : null;
            $model->PoRequired = $attributes['poRequired'] ?? null;
            $model->SalesPersonCode = $attributes['SalesPersonCode'] ?? null;
            $model->SalesPersonName = $attributes['SalesPersonName'] ?? null;
            $model->SalesPersonEmail = $attributes['SalesPersonEmail'] ?? null;
            $model->ProductRestriction = $attributes['productRestriction'] ?? null;
        }

        return $model;
    }

    public function renderSingleCustomerShippingLocation($attributes): ShippingLocation
    {
        $model = new ShippingLocation($attributes);

        if (!empty($attributes)) {
            $model->ShipToNumber = $attributes['shipto'] ?? null;
            $model->ShipToName = $attributes['name'] ?? null;
            $model->ShipToCountryCode = strtoupper($attributes['countrycd'] ?? null);
            $model->ShipToAddress1 = $attributes['addr1'] ?? null;
            $model->ShipToAddress2 = $attributes['addr2'] ?? null;
            $model->ShipToAddress3 = $attributes['addr3'] ?? null;
            $model->ShipToCity = $attributes['city'] ?? null;
            $model->ShipToState = $attributes['state'] ?? null;
            $model->ShipToZipCode = $attributes['zipcd'] ?? null;
            $model->ShipToPhoneNumber = $attributes['phoneno'] ?? null;
            $model->ShipToContact = $attributes['contact'] ?? null;
            $model->ShipToWarehouse = $attributes['whse'] ?? null;
            $model->BackorderCode = $attributes['BackorderCode'] ?? null;
            $model->CarrierCode = $attributes['shipviaty'] ?? null;
            $model->PoRequired = $attributes['poreqfl'] ?? null;
        }

        return $model;
    }

    private function renderSingleProductPriceAvailability($attributes): ProductPriceAvailability
    {
        $model = new ProductPriceAvailability($attributes);

        $model->Warehouses = ErpApi::getWarehouses();

        if (!empty($attributes)) {

            $price = $attributes['extamt'] ?? $attributes['price'];

            $model->ItemNumber = $attributes['prod'] ?? null;
            $model->WarehouseID = $attributes['whse'] ?? null;
            $model->QuantityOnOrder = $attributes['qtyord'] ?? 0;
            $model->Price = !empty($price) ? (float)str_replace([',', '$'], '', $price) : 0;
            $model->ListPrice = $attributes['listprice'] ?? null;
            $model->StandardPrice = $attributes['baseprice'] ?? null;
            $model->QtyBreakExist = $attributes['qtybreakexistfl'] ?? false;
            $model->QtyPrice_1 = $attributes['Price_1'] ?? null;
            $model->QtyBreak_1 = $attributes['quantitybreak1'] ?? null;
            $model->QtyPrice_2 = $attributes['Price_2'] ?? null;
            $model->QtyBreak_2 = $attributes['quantitybreak2'] ?? null;
            $model->QtyPrice_3 = $attributes['Price_3'] ?? null;
            $model->QtyBreak_3 = $attributes['quantitybreak3'] ?? null;
            $model->QtyPrice_4 = $attributes['Price_4'] ?? null;
            $model->QtyBreak_4 = $attributes['quantitybreak4'] ?? null;
            $model->QtyPrice_5 = $attributes['Price_5'] ?? null;
            $model->QtyBreak_5 = $attributes['quantitybreak5'] ?? null;
            $model->QtyPrice_6 = $attributes['Price_6'] ?? null;
            $model->QtyBreak_6 = $attributes['quantitybreak6'] ?? null;
            $model->QtyPrice_7 = $attributes['Price_7'] ?? null;
            $model->QtyBreak_7 = $attributes['quantitybreak7'] ?? null;
            $model->QtyPrice_8 = $attributes['Price_8'] ?? null;
            $model->QtyBreak_8 = $attributes['quantitybreak8'] ?? null;
            $model->QtyPrice_9 = $attributes['Price_9'] ?? null;
            $model->QtyBreak_9 = $attributes['quantitybreak9'] ?? null;
            $model->ExtendedPrice = $attributes['extamt'] ?? null;
            $model->OrderPrice = $model->Price / $model->QuantityOnOrder;
            $model->UnitOfMeasure = $attributes['unit'] ?? null;
            $model->DiscountAmount = $attributes['extdiscount'] ?? 0;
            $model->PricingUnitOfMeasure = ucwords(strtolower($attributes['unit'] ?? null));
            $model->DefaultSellingUnitOfMeasure = $attributes['unit'] ?? null;
            $model->AverageLeadTime = $attributes['leadtmavg'] ?? null;
            $model->QuantityAvailable = $attributes['netavail'] ?? null;
            $model->MinOrderQuantity = $attributes['MOQ'] ?? 1;
            $model->AllowBackOrder = isset($attributes['SANA']) && $attributes['SANA'] == 'yes';
            $model->QuantityInterval = $attributes['SellMult'] ?? null;
            $model->ItemRestricted = isset($attributes['Restricted']) && $attributes['Restricted'] == 'Y';
        }

        return $model;
    }

    private function getPriceBasedOnQtyBreak(ProductPriceAvailability $model, float $orderedQty): float
    {
        $breaks = [
            ['qty' => $model->QtyBreak_9, 'price' => $model->QtyPrice_9],
            ['qty' => $model->QtyBreak_8, 'price' => $model->QtyPrice_8],
            ['qty' => $model->QtyBreak_7, 'price' => $model->QtyPrice_7],
            ['qty' => $model->QtyBreak_6, 'price' => $model->QtyPrice_6],
            ['qty' => $model->QtyBreak_5, 'price' => $model->QtyPrice_5],
            ['qty' => $model->QtyBreak_4, 'price' => $model->QtyPrice_4],
            ['qty' => $model->QtyBreak_3, 'price' => $model->QtyPrice_3],
            ['qty' => $model->QtyBreak_2, 'price' => $model->QtyPrice_2],
            ['qty' => $model->QtyBreak_1, 'price' => $model->QtyPrice_1],
        ];

        foreach ($breaks as $break) {
            if (!empty($break['qty']) && $orderedQty >= $break['qty']) {
                return !empty($break['price'])
                    ? (float)str_replace([',', '$'], '', $break['price'])
                    : 0;
            }
        }

        return $model->Price;
    }

    private function renderProductSync($attributes): ProductSync
    {
        $model = new ProductSync($attributes);

        $model->ItemNumber = $attributes['prod'] ?? null;
        $model->UpdateAction = isset($attributes['preventfl']) && $attributes['preventfl'] == 'y' ? 'DELETE' : 'UPDATE';
        $model->SubAction = $attributes['SubAction'] ?? null;
        $model->Description1 = $attributes['descrip1'] ?? null;
        $model->Description2 = $attributes['descrip2'] ?? null;
        $model->ItemClass = $attributes['ItemClass'] ?? null;
        $model->PriceClass = $attributes['PriceClass'] ?? null;
        $model->ListPrice = (isset($attributes['listprice1']) && is_numeric($attributes['listprice1']))
            ? floatval($attributes['listprice1']) : null;
        $model->UnitOfMeasure = $attributes['unitstock'] ?? $attributes['unit1'];
        $model->PricingUnitOfMeasure = $attributes['unit1'] ?? null;
        $model->Manufacturer = $attributes['Manufacturer'] ?? null;
        $model->PrimaryVendor = $attributes['vendno'] ?? null;

        return $model;
    }

    private function renderSingleWarehouse($warehouse): Warehouse
    {
        $model = new Warehouse($warehouse);

        $model->InternalId = $warehouse['id'] ?? null;
        $model->WarehouseNumber = $warehouse['code'] ?? null;
        $model->WarehouseName = $warehouse['name'] ?? null;
        $model->WarehousePhone = $warehouse['telephone'] ?? null;
        $model->WarehouseZip = $warehouse['zip_code'] ?? null;
        $model->WarehouseAddress = $warehouse['address'] ?? null;
        $model->WarehouseEmail = $warehouse['eamil'] ?? null;
        $model->IsPickUpLocation = $warehouse['pickup_location'] ?? false;

        $model->WhsSeqCode = $warehouse['WhsSeqCode'] ?? null;
        $model->CompanyNumber = $warehouse['CompanyNumber'] ?? null;
        $model->WhPricingLevel = $warehouse['WhPricingLevel'] ?? null;
        $model->ShipVia = $warehouse['ship_via'] ?? null;

        return $model;
    }

    private function renderSingleCreateOrder($attributes): Order
    {
        $model = new Order($attributes);

        if (!empty($attributes)) {
            $model->CustomerNumber = $attributes['CustomerNumber'] ?? null;
            $model->OrderNumber = $attributes['OrderNumber'] ?? null;
            $model->OrderSuffix = $attributes['OrderSuffix'] ?? null;
            $model->OrderType = $attributes['OrderType'] ?? null;

            $model->OrderStatus = $attributes['OrderStatus'] ?? null;
            $model->CustomerName = $attributes['CustomerName'] ?? null;
            $model->BillToCountry = $attributes['BillToCountry'] ?? null;
            $model->CustomerAddress1 = $attributes['CustomerAddress1'] ?? null;

            $model->CustomerAddress2 = $attributes['CustomerAddress2'] ?? null;
            $model->CustomerAddress3 = $attributes['CustomerAddress3'] ?? null;
            $model->BillToCity = $attributes['BillToCity'] ?? null;
            $model->BillToState = $attributes['BillToState'] ?? null;
            $model->BillToZipCode = $attributes['BillToZipCode'] ?? null;

            $model->BillToContact = $attributes['BillToContact'] ?? null;
            $model->ShipToNumber = $attributes['ShipToNumber'] ?? null;
            $model->ShipToName = $attributes['ShipToName'] ?? null;
            $model->ShipToCountry = $attributes['ShipToCountry'] ?? null;
            $model->ShipToAddress1 = $attributes['ShipToAddress1'] ?? null;
            $model->ShipToAddress2 = $attributes['ShipToAddress2'] ?? null;
            $model->ShipToAddress3 = $attributes['ShipToAddress3'] ?? null;
            $model->ShipToCity = $attributes['ShipToCity'] ?? null;

            $model->ShipToState = $attributes['ShipToState'] ?? null;
            $model->ShipToZipCode = $attributes['ShipToZipCode'] ?? null;
            $model->ShipToContact = $attributes['ShipToContact'] ?? null;
            $model->EntryDate = $attributes['EntryDate'] ?? null;
            $model->RequestedShipDate = $attributes['RequestedShipDate'] ?? null;
            $model->CustomerPurchaseOrdernumber = $attributes['CustomerPurchaseOrdernumber'] ?? null;
            $model->ItemSalesAmount = $attributes['ItemSalesAmount'] ?? null;
            $model->DiscountAmountTrading = $attributes['DiscountAmountTrading'] ?? null;
            $model->SalesTaxAmount = $attributes['SalesTaxAmount'] ?? null;
            $model->InvoiceAmount = $attributes['InvoiceAmount'] ?? null;
            $model->FreightAmount = $attributes['FreightAmount'] ?? null;
            $model->TotalOrderValue = $attributes['TotalOrderValue'] ?? null;
            $model->TotalSpecialCharges = $attributes['TotalSpecialCharges'] ?? null;
            $model->CarrierCode = $attributes['CarrierCode'] ?? null;
            $model->WarehouseID = $attributes['WarehouseID'] ?? null;
            $model->InvoiceNumber = $attributes['InvoiceNumber'] ?? null;
            $model->EmailAddress = $attributes['EmailAddress'] ?? null;
            $model->BillToCountryName = $attributes['BillToCountryName'] ?? null;
            $model->ShipToCountryName = $attributes['ShipToCountryName'] ?? null;
            $model->PdfAvailable = $attributes['PdfAvailable'] ?? null;
            $model->OrderDetail = new OrderDetailCollection;

            if (!empty($attributes['OrderDetail'])) {
                foreach (($attributes['OrderDetail'] ?? []) as $orderDetail) {
                    $model->OrderDetail->push($this->renderSingleOrderDetail($orderDetail));
                }
            }
        }

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | INVOICE FUNCTIONS
    |--------------------------------------------------------------------------
    */

    private function orderStatus($code)
    {
        return match ($code) {
            1, 'Ord' => 'Ordered',
            2 => 'Picked',
            3, 'Shp' => 'Shipped',
            4, 'Inv' => 'Invoiced',
            5 => 'Paid',
            9 => 'Cancelled',
            0 => 'Quote',
            11 => 'Payment',
            'Due' => 'Due',
            'Open' => 'Open',
            default => 'Closed'
        };

    }

    private function orderType($code)
    {
        return match ($code) {
            'qu' => 'Quote',
            'so' => 'Order',
            'CR' => 'Credit',
            'PY' => 'Payment',
            default => 'Order'
        };

    }

    private function renderSingleOrder($attributes): Order
    {
        $model = new Order($attributes);

        if (!empty($attributes)) {
            $model->FreightAmount = $attributes['actfreight'] ?? null;
            $model->FreightAccountNumber = $attributes['zFreightAcct'] ?? null;
            $model->ContactId = (string)($attributes['cono'] ?? null);
            $model->CustomerNumber = $attributes['custno'] ?? $attributes['custNo'] ?? null;
            $model->CustomerName = $attributes['name'] ?? null;
            $model->OrderNumber = $attributes['orderno'] ?? $attributes['orderNo'] ?? null;
            $model->OrderNote = $attributes['OrderNote'] ?? null;
            $model->OrderSuffix = isset($attributes['ordersuf']) || isset($attributes['orderSuf'])
                ? str_pad($attributes['ordersuf'] ?? $attributes['orderSuf'], 2, '0', STR_PAD_LEFT)
                : null;
            $model->OrderType = $this->orderType($attributes['transtype'] ?? $attributes['transType'] ?? null);
            $model->OrderStatus = $this->orderStatus($attributes['stage'] ?? $attributes['stageCd'] ?? null);
            $model->CustomerAddress1 = $attributes['addr1'] ?? $attributes['soldtoaddr1'] ?? null;
            $model->CustomerAddress2 = $attributes['addr2'] ?? $attributes['soldtoaddr2'] ?? null;
            $model->CustomerAddress3 = $attributes['addr3'] ?? $attributes['soldtoaddr3'] ?? null;
            $model->BillToCity = $attributes['city'] ?? $attributes['soldtocity'] ?? null;
            $model->BillToState = $attributes['state'] ?? $attributes['soldtost'] ?? null;
            $model->BillToZipCode = $attributes['zipcd'] ?? $attributes['soldtozipcd'] ?? null;
            $model->BillToCountry = mb_strtoupper($attributes['countrycd'] ?? null);
            $model->BillToContact = $attributes['contactid'] ?? null;
            $model->ShipToNumber = $attributes['shipto'] ?? null;
            $model->ShipToName = $attributes['shiptonm'] ?? null;
            $model->ShipToCountry = mb_strtoupper($attributes['zCountryCd'] ?? $attributes['shiptocountrycd'] ?? null);
            $model->ShipToAddress1 = $attributes['shiptoaddr1'] ?? null;
            $model->ShipToAddress2 = $attributes['shiptoaddr2'] ?? null;
            $model->ShipToAddress3 = $attributes['shiptoaddr3'] ?? null;
            $model->ShipToCity = $attributes['shiptocity'] ?? null;
            $model->ShipToState = $attributes['shiptost'] ?? null;
            $model->ShipToZipCode = $attributes['shiptozip'] ?? null;
            $model->ShipToContact = $attributes['contactid'] ?? null;
            $model->EntryDate = $attributes['enterdt'] ?? $attributes['enterDt'] ?? null;
            $model->PromiseDate = $attributes['promisedt'] ?? $attributes['promiseDt'] ?? null;
            $model->InHouseDeliveryDate = $attributes['linelevel-user9'] ?? null;
            $model->RequestedShipDate = $attributes['reqshipdt'] ?? null;
            $model->CustomerPurchaseOrdernumber = $attributes['custpo'] ?? $attributes['custPo'] ?? null;
            $model->ItemSalesAmount = isset($attributes['totlineamt']) || isset($attributes['totLineAmt'])
                ? (float)filter_var(
                    $attributes['totlineamt'] ?? $attributes['totLineAmt'],
                    FILTER_SANITIZE_NUMBER_FLOAT,
                    FILTER_FLAG_ALLOW_FRACTION
                )
                : null; // net amount (post-discount)
            $model->DiscountAmountTrading = isset($attributes['totdiscamt']) ? (float)filter_var($attributes['totdiscamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $model->OrderDiscount = isset($attributes['wodiscamt']) ? (float)filter_var($attributes['wodiscamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $model->SalesTaxAmount = isset($attributes['taxamt']) ? (float)filter_var($attributes['taxamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $model->InvoiceAmount = isset($attributes['totinvamt']) || isset($attributes['totInvAmt'])
                ? (float)filter_var(
                    $attributes['totinvamt'] ?? $attributes['totInvAmt'],
                    FILTER_SANITIZE_NUMBER_FLOAT,
                    FILTER_FLAG_ALLOW_FRACTION
                ) : null;
            $model->TotalSpecialCharges = isset($attributes['totaddonamt']) ? (float)filter_var($attributes['totaddonamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $model->TotalOrderValue = isset($attributes['totlineord']) || isset($attributes['totLineOrd'])
                ? (float)filter_var(
                    $attributes['totlineord'] ?? $attributes['totLineOrd'],
                    FILTER_SANITIZE_NUMBER_FLOAT,
                    FILTER_FLAG_ALLOW_FRACTION
                )
                : null; // gross amount (pre-discount)
            $model->CarrierCode = $attributes['shipviaty'] ?? $attributes['shipviatydesc'] ?? null;
            $model->WarehouseID = mb_strtoupper($attributes['whse'] ?? null);
            $model->InvoiceNumber = $attributes['invno'] ?? null;
            $model->EmailAddress = $attributes['EmailAddress'] ?? null;
            $model->BillToCountryName = mb_strtoupper($attributes['countrycd'] ?? null);
            $model->ShipToCountryName = mb_strtoupper($attributes['shiptocountrycd'] ?? null);
            $model->PdfAvailable = $attributes['PdfAvailable'] ?? 'No';
            $model->SignedType = $attributes['SignedType'] ?? null;
            $model->SignedDoc = $attributes['SignedDoc'] ?? null;
            $model->OrderDisposition = $attributes['orderdisp'] ?? null;
            $model->InvoiceDate = $attributes['invoicedt'] ?? $attributes['invoiceDt'] ?? null;
            $model->OrderDetail = new OrderDetailCollection;
            $model->OrderNotes = new OrderNoteCollection;
            $model->RestockFee = isset($attributes['zRestockAmt'])
                ? (float)filter_var(
                    $attributes['zRestockAmt'],
                    FILTER_SANITIZE_NUMBER_FLOAT,
                    FILTER_FLAG_ALLOW_FRACTION
                )
                : null;

            // if ($model->TotalOrderValue == null) {
            //     $model->TotalOrderValue = floatval($model->InvoiceAmount) + floatval($model->SalesTaxAmount) + floatval($model->FreightAmount) + floatval($model->TotalSpecialCharges) - floatval($model->DiscountAmountTrading);
            // }

            if (!empty($attributes['orderlines'])) {
                foreach (($attributes['orderlines'] ?? []) as $orderDetail) {
                    $orderDetail['lineLevelFieldMap'] = $attributes['lineLevelFieldMap'] ?? [];
                    $model->OrderDetail->push($this->renderSingleOrderDetail($orderDetail));
                }
            }

            if (!empty($attributes['OrderNotes'])) {
                foreach (($attributes['OrderNotes'] ?? []) as $orderNote) {
                    $model->OrderNotes->push($this->renderSingleOrderNote($orderNote));
                }
            }

            $model->ExtraCharges = $this->renderExtraCharges($attributes);
        }

        return $model;
    }

    private function renderSingleOrderNote($attributes)
    {
        $model = new OrderNote($attributes);

        if (!empty($attributes)) {
            $model->Subject = $attributes['Subject'] ?? null;
            $model->Date = isset($attributes['Date']) ? CarbonImmutable::parse($attributes['Date']) : null;
            $model->NoteNum = $attributes['NoteNum'] ?? null;
            $model->Type = $attributes['Type'] ?? null;
            $model->Editable = $attributes['Editable'] ?? null;
            $model->Note = $attributes['Note'] ?? $attributes['notetext'] ?? null;
            $model->Secureflag = $attributes['securefl'] ?? null;
        }

        return $model;

    }

    private function renderSingleOrderDetail(array $attributes): OrderDetail
    {
        $model = new OrderDetail($attributes);

        if (!empty($attributes)) {
            // Basic line/item info
            $model->LineNumber = $attributes['lineNo'] ?? null;
            $model->ItemNumber = $attributes['prod'] ?? null;
            $model->ItemType = $attributes['ItemType'] ?? null;
            $model->ItemDescription1 = $attributes['desc1'] ?? null;
            $model->ItemDescription2 = $attributes['desc2'] ?? null;

            // Quantity details
            $model->QuantityOrdered = $attributes['qtyOrd'] ?? $attributes['Quantity'] ?? null;
            $model->QuantityShipped = $attributes['qtyShip'] ?? null;
            $model->QuantityBackordered = isset($model->QuantityOrdered, $model->QuantityShipped)
                ? $model->QuantityOrdered - $model->QuantityShipped
                : null;

            $model->UnitOfMeasure = $attributes['unit'] ?? null;

            // Pricing & amounts
            $model->ActualSellPrice = $attributes['netOrd'] ?? null;
            $model->TotalLineAmount = $attributes['netAmt'] ?? null;
            $model->PricingUM = (isset($model->QuantityOrdered, $model->ActualSellPrice)
                && $model->ActualSellPrice != 0)
                ? $model->ActualSellPrice / $model->QuantityOrdered
                : null;
            $model->TiedOrder = !empty($attributes['tiedorder']) ?
                str_replace('PO# ', '', $attributes['tiedorder']) : '';
            $model->PODetails = $this->getPODetails([
                'poNumber' => $attributes['tiedorder'],
                'productCode' => $attributes['prod'],
            ]);
            $model->DirectOrder = $attributes['botype'] ?? null;
            // Shipping & order info
            $model->ConvertedToOrder = $attributes['ConvertedToOrder'] ?? null;
            $model->ShipWhse = $attributes['ShipWhse'] ?? null;

            // In-House Delivery Date based on seqNo mapping
            if (!empty($attributes['lineNo']) && !empty($attributes['lineLevelFieldMap'])) {
                $lineMap = $attributes['lineLevelFieldMap'][$attributes['lineNo']] ?? [];

                $model->InHouseDeliveryDate = $lineMap['user9'] ?? null;
                $model->LineShipVia = $lineMap['zLineShipVia'] ?? null;
                $model->LineFrtTerms = $lineMap['zLineFrtTerms'] ?? null;
                $model->LineFrtBillAcct = $lineMap['zLineFrtBillAcct'] ?? null;
            }

        }

        return $model;
    }

    private function renderSingleCreateQuotation($attributes): CreateQuotation
    {
        $model = new CreateQuotation($attributes);

        if (!empty($attributes)) {
            $model->OrderNumber = floatval(str_replace(['$', ','], '', ($attributes['OrderNumber'] ?? '')));
            $model->SalesTaxAmount = floatval(str_replace(['$', ','], '', ($attributes['SalesTaxAmount'] ?? '')));
            $model->FreightAmount = !empty($attributes['FreightAmount']) ? floatval($attributes['FreightAmount']) : null;
            $model->TotalOrderValue = floatval(str_replace(['$', ','], '', ($attributes['TotalOrderValue'] ?? '')));
        }

        return $model;
    }

    private function renderSingleQuotation($attributes): Quotation
    {
        $model = new Quotation($attributes);

        if (!empty($attributes)) {
            $model->CustomerNumber = $attributes['custno'] ?? $attributes['custNo'] ?? null;
            $model->QuoteNumber = $attributes['orderno'] ?? $attributes['orderNo'] ?? null;
            $model->Suffix = isset($attributes['ordersuf']) || isset($attributes['orderSuf'])
                ? str_pad($attributes['ordersuf'] ?? $attributes['orderSuf'], 2, '0', STR_PAD_LEFT)
                : null;
            $model->CustomerName = $attributes['name'] ?? null;
            $model->BillToCountry = mb_strtoupper($attributes['countrycd'] ?? null);
            $model->CustomerAddress1 = $attributes['addr1'] ?? null;
            $model->CustomerAddress2 = $attributes['addr2'] ?? null;
            $model->CustomerAddress3 = $attributes['addr3'] ?? null;
            $model->BillToCity = $attributes['city'] ?? null;
            $model->BillToState = $attributes['state'] ?? null;
            $model->BillToZipCode = $attributes['zipcd'] ?? null;
            $model->BillToContact = $attributes['contactid'] ?? null;
            $model->ShipToNumber = $attributes['shipto'] ?? null;
            $model->ShipToName = $attributes['shiptonm'] ?? null;
            $model->ShipToCountry = mb_strtoupper($attributes['shiptocountrycd'] ?? null);
            $model->ShipToAddress1 = $attributes['shiptoaddr1'] ?? null;
            $model->ShipToAddress2 = $attributes['shiptoaddr2'] ?? null;
            $model->ShipToAddress3 = $attributes['shiptoaddr3'] ?? null;
            $model->ShipToCity = $attributes['shiptocity'] ?? null;
            $model->ShipToState = $attributes['shiptost'] ?? null;
            $model->ShipToZipCode = $attributes['shiptozip'] ?? null;
            $model->ShipToContact = $attributes['contactid'] ?? null;
            $model->EntryDate = $attributes['enterdt'] ?? $attributes['enterDt'] ?? null;
            $model->EffectiveDate = $attributes['EffectiveDate'] ?? null;
            $model->ExpirationDate = $attributes['ExpirationDate'] ?? null;
            $model->CustomerPurchaseOrdernumber = $attributes['custpo'] ?? $attributes['custPo'] ?? null;
            $model->Title = $attributes['Title'] ?? null;
            $model->QuotedTo = $attributes['QuotedTo'] ?? null;
            $model->QuotedBy = $attributes['QuotedBy'] ?? null;
            $model->QuotedByEmail = $attributes['QuotedByEmail'] ?? null;
            $model->QuoteType = $this->orderType($attributes['transtype'] ?? $attributes['transType'] ?? null);
            $model->OrderStatus = $this->orderStatus($attributes['stage'] ?? $attributes['stageCd'] ?? null);

            $model->ItemSalesAmount = isset($attributes['totlineamt']) || isset($attributes['totLineAmt'])
                ? (float)filter_var($attributes['totlineamt'] ?? $attributes['totLineAmt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
                : null;

            $model->DiscountAmountTrading = isset($attributes['totdiscamt'])
                ? (float)filter_var($attributes['totdiscamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
                : null;

            $model->SalesTaxAmount = isset($attributes['taxamt'])
                ? (float)filter_var($attributes['taxamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
                : null;

            $model->FreightAmount = $attributes['actfreight'] ?? null;

            $model->QuoteAmount = isset($attributes['totinvamt']) || isset($attributes['totInvAmt'])
                ? (float)filter_var($attributes['totinvamt'] ?? $attributes['totInvAmt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
                : null;

            $model->TotalOrderValue = isset($attributes['totinvamt']) || isset($attributes['totInvAmt'])
                ? (float)filter_var($attributes['totinvamt'] ?? $attributes['totInvAmt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
                : null;

            $model->CarrierCode = $attributes['shipviaty'] ?? $attributes['shipviatydesc'] ?? null;
            $model->WarehouseID = mb_strtoupper($attributes['whse'] ?? null);
            $model->EmailAddress = $attributes['EmailAddress'] ?? null;
            $model->BillToCountryName = mb_strtoupper($attributes['countrycd'] ?? null);
            $model->ShipToCountryName = mb_strtoupper($attributes['shiptocountrycd'] ?? null);
            $model->PdfAvailable = $attributes['PdfAvailable'] ?? 'No';
            $model->QuoteDetail = new OrderDetailCollection;

            // if ($model->TotalOrderValue == null) {
            //     $model->TotalOrderValue = floatval($model->QuoteAmount) + floatval($model->SalesTaxAmount) + floatval($model->FreightAmount) - floatval($model->DiscountAmountTrading);
            // }

            if (!empty($attributes['orderlines'])) {
                foreach (($attributes['orderlines'] ?? []) as $orderDetail) {
                    $model->QuoteDetail->push($this->renderSingleOrderDetail($orderDetail));
                }
            }
        }

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    private function renderSingleInvoice($attributes, string $invoiceStatus = 'PAID'): Invoice
    {
        $model = new Invoice($attributes);

        if (!empty($attributes)) {
            $model->FreightAccountNumber = $attributes['zFreightAcct'] ?? null;
            $model->AllowArPayments = $attributes['AllowArPayments'] ?? 'No';
            $model->InvoiceNumber = $attributes['invnoraw'] ?? $attributes['orderno'] ?? null;
            $model->InvoiceSuffix = isset($attributes['ordersuf']) || isset($attributes['invsufraw'])
                ? str_pad($attributes['ordersuf'] ?? $attributes['invsufraw'], 2, '0', STR_PAD_LEFT)
                : null;
            $model->InvoiceType = $this->orderType($attributes['transcd'] ?? $attributes['transtype'] ?? null);
            $model->InvoiceStatus = $this->orderStatus($attributes['statustype'] ?? $attributes['stage'] ?? null);
            $model->InvoiceDisputeCode = $attributes['disputefl'] ?? null;
            $model->FinanceChargeFlag = $attributes['FinanceChargeFlag'] ?? null;
            $model->InvoiceDate = $attributes['invdt'] ?? $attributes['invoicedt'] ?? null;
            $model->AgeDate = $attributes['agedt'] ?? null;
            $model->EntryDate = $attributes['enterdt'] ?? null;
            $model->InvoiceAmount = $attributes['amountx'] ?? $attributes['totinvamt'] ?? null;
            $model->InvoiceBalance = $attributes['amtduex'] ?? null;
            $model->InvoiceDueDate = $attributes['duedt'] ?? null;
            $model->PendingPayment = $attributes['PendingPayment'] ?? null;
            $model->DiscountAmount = $attributes['disctakenamt'] ?? null;
            $model->DiscountDueDate = $attributes['discdt'] ?? null;
            $model->LastTransactionDate = $attributes['lasttransdt'] ?? null;
            $model->PayDays = $attributes['paydays'] ?? null;
            $model->CustomerPONumber = $attributes['custpo'] ?? $attributes['custpono'] ?? null;
            $model->HasInvoiceDetail = 'Yes';
            $model->OrderNumber = $attributes['orderno'] ?? null;

            $model->ShipToName = $attributes['shiptonm'] ?? null;
            $model->ShipToAddress1 = $attributes['shiptoaddr1'] ?? null;
            $model->ShipToAddress2 = $attributes['shiptoaddr2'] ?? null;
            $model->ShipToCity = $attributes['shiptocity'] ?? null;
            $model->ShipToState = $attributes['shiptost'] ?? null;
            $model->ShipToZipCode = $attributes['shiptozip'] ?? null;
            $model->ShipToAddress3 = $attributes['shiptoaddr3'] ?? null;
            $model->ShipToCountry = mb_strtoupper($attributes['zCountryCd'] ?? $attributes['shiptocountrycd'] ?? null);
            $model->WarehouseID = mb_strtoupper($attributes['whse'] ?? null);
            $model->OrderDisposition = $attributes['orderdisp'] ?? null;
            $model->CarrierCode = $attributes['shipviaty'] ?? $attributes['shipviatydesc'] ?? null;
            $model->DiscountAmountTrading = isset($attributes['totdiscamt']) ? (float)filter_var($attributes['totdiscamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $model->TotalSpecialCharges = isset($attributes['totaddonamt']) ? (float)filter_var($attributes['totaddonamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $model->FreightAmount = isset($attributes['taxamt']) ? (float)filter_var($attributes['taxamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $model->SalesTaxAmount = isset($attributes['taxamt']) ? (float)filter_var($attributes['taxamt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $model->TotalOrderValue = isset($attributes['totlineord']) || isset($attributes['totLineOrd'])
                ? (float)filter_var(
                    $attributes['totlineord'] ?? $attributes['totLineOrd'],
                    FILTER_SANITIZE_NUMBER_FLOAT,
                    FILTER_FLAG_ALLOW_FRACTION
                )
                : null;
            $model->ItemSalesAmount = isset($attributes['totlineamt']) || isset($attributes['totLineAmt'])
                ? (float)filter_var(
                    $attributes['totlineamt'] ?? $attributes['totLineAmt'],
                    FILTER_SANITIZE_NUMBER_FLOAT,
                    FILTER_FLAG_ALLOW_FRACTION
                )
                : null; // net amount (post-discount)
            $model->DaysOpen = $model->InvoiceDate
                ? Carbon::parse($model->InvoiceDate)->diffInDays(Carbon::now())
                : null;

            $model->RestockFee = isset($attributes['zRestockAmt'])
                ? (float)filter_var(
                    $attributes['zRestockAmt'],
                    FILTER_SANITIZE_NUMBER_FLOAT,
                    FILTER_FLAG_ALLOW_FRACTION
                )
                : null;

            // if ($model->TotalOrderValue == null) {
            //     $model->TotalOrderValue = floatval($model->InvoiceAmount) + floatval($model->SalesTaxAmount) + floatval($model->FreightAmount) + floatval($model->TotalSpecialCharges) - floatval($model->DiscountAmountTrading);
            // }
            $model->InvoiceDetail = new OrderDetailCollection;

            if (!empty($attributes['orderlines'])) {
                foreach (($attributes['orderlines'] ?? []) as $orderDetail) {
                    $orderDetail['lineLevelFieldMap'] = $attributes['lineLevelFieldMap'] ?? [];
                    $model->InvoiceDetail->push($this->renderSingleOrderDetail($orderDetail));
                }
            }

            $model->ExtraCharges = $this->renderExtraCharges($attributes);

        }

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | CAMPAIGN FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function getCampaignList(array $attributes = []): CampaignCollection
    {
        $campaignList = new CampaignCollection;

        if (!empty($attributes)) {
            foreach (($attributes['ItemPromoHeader'] ?? []) as $campaign) {
                $campaignList->push($this->renderSingleCampaign($campaign));
            }
        }

        return $campaignList;
    }

    public function getCampaignDetail(array $data = []): Campaign
    {
        $data = isset($data['ItemPromoHeader']) ? array_shift($data['ItemPromoHeader']) : [];

        return $this->renderSingleCampaign($data);
    }

    private function renderSingleCampaign($attributes): Campaign
    {
        $model = new Campaign($attributes);

        if (!empty($attributes)) {
            $model->Promoid = $attributes['Promoid'] ?? null;
            $model->BegDate = $attributes['BegDate'] ?? null;
            $model->EndDate = $attributes['EndDate'] ?? null;
            $model->ShortDesc = $attributes['ShortDesc'] ?? null;
            $model->Hashtag = $attributes['Hashtag'] ?? null;
            $model->LongDesc = $attributes['LongDesc'] ?? null;
            $model->ImagePath = $attributes['ImagePath'] ?? null;
            $model->Clearance = $attributes['Clearance'] ?? null;
            $model->Online = $attributes['Online'] ?? null;
            $model->Inside = $attributes['Inside'] ?? null;
            $model->Print = $attributes['Print'] ?? null;
            $model->Sort = $attributes['Sort'] ?? null;
            $model->Private = $attributes['Private'] ?? null;
            $model->CampaignDetail = new CampaignDetailCollection;

            if (!empty($attributes['ItemPromoDetails'])) {
                foreach (($attributes['ItemPromoDetails'] ?? []) as $campaignDetail) {
                    $model->CampaignDetail->push($this->renderSingleCampaignDetail($campaignDetail));
                }
            }
        }

        return $model;
    }

    private function renderSingleCampaignDetail($attributes): CampaignDetail
    {
        $model = new CampaignDetail($attributes);

        if (!empty($attributes)) {
            $model->Promoid = $attributes['Promoid'] ?? null;
            $model->Item = $attributes['Item'] ?? null;
            $model->ItemDescription = $attributes['ItemDescription'] ?? null;
            $model->ExtDescription = $attributes['ExtDescription'] ?? null;
            $model->Attributes = $attributes['Attributes'] ?? null;
            $model->ItemID = $attributes['ItemID'] ?? null;
            $model->ItemImagePath = $attributes['ItemImagePath'] ?? null;
            $model->Price = $attributes['Price'] ?? null;
            $model->PriceUM = $attributes['PriceUM'] ?? null;
            $model->Sort = $attributes['Sort'] ?? null;
        }

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTACT VALIDATION FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function contactValidation(array $inputs = []): ContactValidation
    {
        $model = new ContactValidation($inputs);

        $model->ValidCombination = $inputs['ValidCombination'] ?? 'N';
        $model->CustomerNumber = $inputs['CustomerNumber'] ?? null;
        $model->CustomerName = $inputs['CustomerName'] ?? null;
        $model->CustomerCity = $inputs['CustomerCity'] ?? null;
        $model->CustomerState = $inputs['CustomerState'] ?? null;
        $model->CustomerAddress = trim(implode(' ', [($inputs['CustomerAddress1'] ?? null), ($inputs['CustomerAddress2'] ?? null), ($inputs['CustomerAddress3'] ?? null)]));
        $model->CustomerCountry = $inputs['CustomerCountry'] ?? null;
        $model->CustomerZipCode = $inputs['CustomerZipCode'] ?? null;
        $model->ContactNumber = $inputs['ContactNumber'] ?? null;
        $model->EmailAddress = $inputs['CustomerEmail'] ?? null;
        $model->DefaultWarehouse = $inputs['DefaultWarehouse'] ?? null;
        $model->DefaultShipTo = $inputs['DefaultShipTo'] ?? null;

        return $model;
    }

    /**
     * This API is to get customer AR Open Invoice data from the FACTS ERP.
     */
    private function getCylinderDetail(array $attributes = []): Cylinders
    {
        $model = new Cylinders($attributes);

        if (!empty($attributes)) {
            $model->Cylinder = $attributes['Cylinder'];
            $model->Beginning = $attributes['Beginning'];
            $model->Delivered = $attributes['Delivered'];
            $model->Returned = $attributes['Returned'];
            $model->Balance = $attributes['Balance'];
            $model->LastDelivery = $attributes['Last Delivery'];
            $model->LastReturned = $attributes['Last returned'];
        }

        return $model;
    }

    public function getCylinders(array $attributes = []): CylinderCollection
    {
        $cylinderList = new CylinderCollection;

        if (!empty($attributes)) {
            foreach (($attributes['Cylinders'] ?? []) as $cylinder) {
                $cylinderList->push($this->getCylinderDetail($cylinder));
            }
        }

        return $cylinderList;
    }

    /**
     * @throws InvalidBase64Data
     */
    public function getDocument(array $inputs = []): Document
    {
        $document = new Document($inputs);

        if (!empty($inputs)) {

            $attributes = $inputs['DocumentData'];

            $document->DocumentType = 'Invoice';

            $document->File = $this->convertBase64ToFile($attributes['PDF_Blob']);

        }

        return $document;
    }

    /**
     * This API is to get customer past items from the FACTS ERP.
     */
    public function getPastItemList(array $attributes = []): PastItemCollection
    {
        $pastItemList = new PastItemCollection;

        if (!empty($attributes)) {
            foreach (($attributes['tOrditemhist']['t-orditemhist'] ?? []) as $attribute) {
                $pastItemList->push($this->renderSinglePastItem($attribute));
            }
        }

        return $pastItemList;
    }

    private function renderSinglePastItem(array $attributes = []): PastItem
    {
        $model = new PastItem($attributes);

        if (!empty($attributes)) {
            $model->ItemNumber = $attributes['prod'] ?? null;
            $model->WebItem = $attributes['WebItem'] ?? null;
            $model->History = $attributes['History'] ?? null;
        }

        return $model;
    }

    /**
     * Map raw FetchWhere `ttblsmsew` response into month-by-month sales summary.
     *
     * Returns an array of 12 month rows with keys:
     * - month: Month name
     * - year: 4-digit year
     * - quantity_purchased: total qty for that month
     * - average_purchase_price: numeric (float) average price (sales/qty) or 0.0
     * - average_purchase_price_formatted: string like "$0.00"
     */
    public function getPastSalesHistory(array $attributes = []): array
    {
        $rows = [];

        $monthNames = [
            'January','February','March','April','May','June',
            'July','August','September','October','November','December'
        ];

        // Initialize accumulators
        $qtyByMonth = array_fill(1, 12, 0.0);
        $salesByMonth = array_fill(1, 12, 0.0);

        $records = $attributes['ttblsmsew'] ?? [];

        foreach ($records as $rec) {
            if (isset($rec['yr']) && $rec['yr'] !== null) {
                $yr = intval($rec['yr']);
                if ($yr < 100) {
                    $detectedYear = 2000 + $yr;
                } elseif ($yr > 999) {
                    $detectedYear = $yr;
                } else {
                    $detectedYear = $yr;
                }
                break;
            }
            if (!empty($rec['lastpurdt'])) {
                try {
                    $d = new \DateTime($rec['lastpurdt']);
                    $detectedYear = intval($d->format('Y'));
                    break;
                } catch (\Exception $e) {
                }
            }
            if (!empty($rec['transdt'])) {
                try {
                    $d = new \DateTime($rec['transdt']);
                    $detectedYear = intval($d->format('Y'));
                    break;
                } catch (\Exception $e) {
                }
            }
        }

        $detectedYear = $detectedYear ?? $attributes['year'] ?? intval(date('Y'));

        // Aggregate qty and sales per month index
        foreach ($records as $rec) {
            $qtysold = $rec['qtysold'] ?? [];
            $salesamt = $rec['salesamt'] ?? [];

            for ($i = 0; $i < 12; $i++) {
                $mIndex = $i + 1;
                $q = isset($qtysold[$i]) ? floatval($qtysold[$i]) : 0.0;
                $s = isset($salesamt[$i]) ? floatval($salesamt[$i]) : 0.0;

                $qtyByMonth[$mIndex] += $q;
                $salesByMonth[$mIndex] += $s;
            }
        }

        // Build rows
        for ($i = 1; $i <= 12; $i++) {
            $qty = $qtyByMonth[$i];
            $sales = $salesByMonth[$i];
            $avg = 0.0;
            if ($qty > 0) {
                $avg = $sales / $qty;
            }

            // If average is zero, show two decimals ($0.00). Otherwise show three decimals.
            if (abs($avg) < 0.0000001) {
                $avgNumeric = 0.0;
                $avgFormatted = '$' . number_format(0, 2);
            } else {
                $avgNumeric = round($avg, 3);
                $avgFormatted = '$' . number_format($avgNumeric, 3);
            }

            $rows[] = [
                'month' => $monthNames[$i - 1],
                'year' => $detectedYear,
                'quantity_purchased' => (int) round($qty),
                'average_purchase_price' => $avgNumeric,
                'average_purchase_price_formatted' => $avgFormatted,
            ];
        }

        return [
            'months' => $rows,
            'raw' => $attributes,
        ];
    }

    /**
     * This API is to get shipping tracking URL
     */
    public function getTrackShipment(array $attributes = []): TrackShipmentCollection
    {
        $model = new TrackShipmentCollection;

        foreach (($attributes['tTrackernum']['t-trackernum'] ?? []) as $trackingInfo) {
            $model->push($this->renderSingleTrackShipment($trackingInfo));
        }

        return $model;
    }

    public function getTermsType(array $attributes = []): TermsType
    {
        $model = new TermsType($attributes);

        $termsTypeValue = null;
        if (!empty($attributes['tFieldlist']['t-fieldlist'])) {
            foreach ($attributes['tFieldlist']['t-fieldlist'] as $field) {
                if ($field['fieldName'] === 'termstype') {
                    $termsTypeValue = $field['fieldValue'];
                    break;
                }
            }
        }
        $model->TermsType = $termsTypeValue;

        return $model;
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
     * @since 2024.12.8354871
     */
    public function createUpdateContact(array $attributes = []): Contact
    {
        return new Contact($attributes);
    }

    /**
     * This API is to get customer entity information from the CSD ERP
     *
     *
     * @done
     *
     * @todo Adapter mapping pending
     *
     * @since 2024.12.8354871
     */
    public function getContactList(array $filters = []): ContactCollection
    {
        $collection = new ContactCollection;

        $attributes = $filters['tCamcontactv4']['t-camcontactv4'] ?? [];

        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                $collection->push($this->renderSingleContact($attribute));
            }
        }

        return $collection;
    }

    private function renderSingleContact($attributes = []): Contact
    {
        $model = new Contact($attributes);

        if (!empty($attributes)) {
            $model->ContactNumber = $attributes['contactid'] ? (string)intval($attributes['contactid']) : null;
            $model->ContactName = trim(implode(' ', [$attributes['firstnm'] ?? null, $attributes['middlenm'] ?? null, $attributes['lastnm'] ?? null]));
            $model->AccountTitle = $attributes['cotitle'] ?? null;
            $model->AccountTitleCode = $attributes['contacttype'] ?? null;
            $model->AccountTitleDesc = $attributes['contacttypedesc'] ?? null;
            $model->ContactPhone = $attributes['phoneno'] ?? $attributes['firstphoneno'] ?? null;
            $model->ContactEmail = Str::lower($attributes['emailaddr'] ?? $attributes['firstemailaddr'] ?? '');
            $model->ContactAddress1 = $attributes['addr1'] ?? null;
            $model->ContactAddress2 = $attributes['addr2'] ?? null;
            $model->ContactCity = $attributes['city'] ?? null;
            $model->ContactState = $attributes['state'] ?? null;
            $model->ContactZipCode = $attributes['zipcd'] ?? null;
            $model->CustomerNumber = $attributes['firstprimarykey'] ?? null;
            $model->Comment = $attributes['comment'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to get customer entity information from the CSD ERP
     *
     * @done
     *
     * @since 2024.12.8354871
     */
    public function getContactDetail(array $filters = []): Contact
    {
        $model = new Contact($filters);

        $attributes = $filters['tCamcontactv4']['t-camcontactv4'] ?? [[]];

        if (!empty($attributes)) {
            return $this->renderSingleContact(array_shift($attributes));
        }

        return $model;

    }

    public function renderSingleTrackShipment($attributes = []): TrackShipment
    {
        $model = new TrackShipment($attributes);

        $model->OrderNumber = $attributes['orderno'] ?? null;
        $model->TrackerNo = $attributes['trackerno'] ?? null;
        $model->ShipViaType = $attributes['shipviaty'] ?? null;

        return $model;
    }

    public function getNotesList(array $inputs = []): OrderNoteCollection
    {
        $collection = new OrderNoteCollection;
        foreach ($inputs['tNotes']['t-notes'] ?? [] as $input) {
            $collection->push($this->renderSingleOrderNote($input));
        }

        return $collection;
    }

    public function getInvoiceTransaction(array $inputs = []): InvoiceTransactionCollection
    {
        $collection = new InvoiceTransactionCollection;
        foreach ($inputs['tArinvdata']['t-arinvdata'] ?? [] as $input) {
            $collection->push($this->renderSingleInvoiceTransaction($input));
        }

        return $collection;
    }

    public function renderSingleInvoiceTransaction($attributes = []): InvoiceTransaction
    {
        $model = new InvoiceTransaction($attributes);

        $model->TransactionDate = $attributes['transdate'] ?? null;
        $model->TransactionType = $attributes['transtype'] ?? null;
        $model->TransactionAmount = $attributes['transamt'] ?? null;
        $model->PaymentAmount = $attributes['payamt'] ?? null;
        $model->CashDiscountAmount = $attributes['disctakenamt'] ?? null;
        $model->CheckNumber = $attributes['checkno'] ?? null;
        $model->AdjustmentNumber = $attributes['adjno'] ?? null;
        $model->OrderNumber = $attributes['orderno'] ?? null;
        $model->PurchaseOrderNumber = $attributes['custpono'] ?? null;
        $model->OrderSuffix = isset($attributes['ordersuf'])
            ? str_pad($attributes['ordersuf'], 2, '0', STR_PAD_LEFT)
            : null;

        return $model;
    }

    private function getPODetails(array $inputs = [])
    {
        if (!in_array(config('amplify.client_code'), ['NUX', 'DKL']) || empty($inputs['poNumber'])) {
            return [];
        }

        $poDetails = Cache::remember(
            'po_details_' . $inputs['poNumber'],
            now()->addMinutes(5),
            function () use ($inputs) {
                return ErpApi::getPODetails($inputs);
            }
        );

        if (empty($poDetails['tPolineitemv2']['t-polineitemv2'])) {
            return [];
        }

        $productCode = $inputs['productCode'];
        $filteredPoDetails = array_filter($poDetails['tPolineitemv2']['t-polineitemv2'], function ($item) use ($productCode) {
            return $item['shipprod'] === $productCode;
        });

        $orderPODetails = array_shift($filteredPoDetails);
        $model = new OrderPODetails($orderPODetails);

        if (!empty($orderPODetails)) {
            $model->BoType = $this->clearFix($orderPODetails['botype']);
            $model->CommentFl = $this->clearFix($orderPODetails['commentfl']);
            $model->ContNo = $this->clearFix($orderPODetails['contno']);
            $model->Cubes = $this->clearFix($orderPODetails['cubes']);
            $model->DueDate = $this->clearFix($orderPODetails['duedt']);
            $model->EnterDate = $this->clearFix($orderPODetails['enterdt']);
            $model->ExpShipDate = $this->clearFix($orderPODetails['expshipdt']);
            $model->LeadOverTy = $this->clearFix($orderPODetails['leadoverty']);
            $model->LineNo = $this->clearFix($orderPODetails['lineno']);
            $model->NetAmt = $this->clearFix($orderPODetails['netamt']);
            $model->NetRcv = $this->clearFix($orderPODetails['netrcv']);
            $model->NonStockTy = $this->clearFix($orderPODetails['nonstockty']);
            $model->Price = $this->clearFix($orderPODetails['price']);
            $model->PrintFl = $this->clearFix($orderPODetails['printfl']);
            $model->ProdCat = $this->clearFix($orderPODetails['prodcat']);
            $model->ProdCatDesc = $this->clearFix($orderPODetails['prodcatdesc']);
            $model->ProdDesc = $this->clearFix($orderPODetails['proddesc']);
            $model->ProdDesc2 = $this->clearFix($orderPODetails['proddesc2']);
            $model->ProdLine = $this->clearFix($orderPODetails['prodline']);
            $model->QtyOrd = $this->clearFix($orderPODetails['qtyord']);
            $model->QtyRcv = $this->clearFix($orderPODetails['qtyrcv']);
            $model->QtyUnAvail = $this->clearFix($orderPODetails['qtyunavail']);
            $model->RcvCost = $this->clearFix($orderPODetails['rcvcost']);
            $model->ReasUnavTy = $this->clearFix($orderPODetails['reasunavty']);
            $model->ReqProd = $this->clearFix($orderPODetails['reqprod']);
            $model->ReqShipDt = $this->clearFix($orderPODetails['reqshipdt']);
            $model->ShipProd = $this->clearFix($orderPODetails['shipprod']);
            $model->StatusType = $this->clearFix($orderPODetails['statustype']);
            $model->StkQtyOrd = $this->clearFix($orderPODetails['stkqtyord']);
            $model->StkQtyRcv = $this->clearFix($orderPODetails['stkqtyrcv']);
            $model->TallyFl = $this->clearFix($orderPODetails['tallyfl']);
            $model->TrackNo = $this->clearFix($orderPODetails['trackno']);
            $model->Unit = $this->clearFix($orderPODetails['unit']);
            $model->UnitConv = $this->clearFix($orderPODetails['unitconv']);
            $model->VaFakeProdFl = $this->clearFix($orderPODetails['vafakeprodfl']);
            $model->Weight = $this->clearFix($orderPODetails['weight']);
            $model->RcvUnAvailFl = $this->clearFix($orderPODetails['rcvunavailfl']);
            $model->SortFld = $this->clearFix($orderPODetails['sortFld']);
        }

        return $model;
    }

    private function clearFix($value): mixed
    {
        if (empty($value)) {
            return '';
        }

        return $value;
    }


    /**
     * Render printable document from IDM JSON response
     */
   public function renderPrintableDocument(array $response): Document
    {
        $document = new Document($response);

        $items = $response['items']['item'] ?? null;

        if (empty($items) || !is_array($items)) {
            return $document;
        }

        // First item = latest (already sorted DESC)
        $item = $items[0] ?? null;

        if (
            !$item ||
            empty($item['resrs']['res']) ||
            !is_array($item['resrs']['res'])
        ) {
            return $document;
        }

        foreach ($item['resrs']['res'] as $resource) {
            if (($resource['mimetype'] ?? null) === 'application/pdf') {
                $document->DocumentType = 'PDF';
                $document->File = $resource['url'] ?? null;

                return $document;
            }
        }

        return $document;
    }


}
