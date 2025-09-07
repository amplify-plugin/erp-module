<?php

namespace Amplify\ErpApi\Adapters;

use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\CampaignDetailCollection;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\CreateQuotationCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Collections\OrderCollection;
use Amplify\ErpApi\Collections\OrderDetailCollection;
use Amplify\ErpApi\Collections\OrderNoteCollection;
use Amplify\ErpApi\Collections\PastItemCollection;
use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Collections\ProductSyncCollection;
use Amplify\ErpApi\Collections\QuotationCollection;
use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\TrackShipmentCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Interfaces\ErpApiInterface;
use Amplify\ErpApi\Wrappers\Campaign;
use Amplify\ErpApi\Wrappers\CampaignDetail;
use Amplify\ErpApi\Wrappers\Contact;
use Amplify\ErpApi\Wrappers\ContactValidation;
use Amplify\ErpApi\Wrappers\CreateCustomer;
use Amplify\ErpApi\Wrappers\CreateOrUpdateNote;
use Amplify\ErpApi\Wrappers\CreatePayment;
use Amplify\ErpApi\Wrappers\CreateQuotation;
use Amplify\ErpApi\Wrappers\Customer;
use Amplify\ErpApi\Wrappers\CustomerAR;
use Amplify\ErpApi\Wrappers\Document;
use Amplify\ErpApi\Wrappers\Invoice;
use Amplify\ErpApi\Wrappers\Order;
use Amplify\ErpApi\Wrappers\OrderDetail;
use Amplify\ErpApi\Wrappers\OrderNote;
use Amplify\ErpApi\Wrappers\OrderTotal;
use Amplify\ErpApi\Wrappers\ProductPriceAvailability;
use Amplify\ErpApi\Wrappers\ProductSync;
use Amplify\ErpApi\Wrappers\Quotation;
use Amplify\ErpApi\Wrappers\ShippingLocation;
use Amplify\ErpApi\Wrappers\ShippingLocationValidation;
use Amplify\ErpApi\Wrappers\ShippingOption;
use Amplify\ErpApi\Wrappers\TrackShipment;
use Amplify\ErpApi\Wrappers\Warehouse;
use Carbon\CarbonImmutable;

class FactsErp77Adapter implements ErpApiInterface
{
    use \Amplify\ErpApi\Traits\ManageDocumentTrait;

    /*
    |--------------------------------------------------------------------------
    | UTILITY FUNCTIONS
    |--------------------------------------------------------------------------
    */

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
            $model->CarrierDescription = $datum['name'];
            $model->Driver = $datum['driver'];

            $collection->push($model);
        }

        return $collection;
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
        $model = new CreateCustomer($attributes);

        if (! empty($attributes)) {
            $attributes = $attributes['CashCustomer'] ?? [];
            $model->NewAccountNumber = $attributes['NewAccountNumber'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     */
    public function getCustomerList(array $customers = []): CustomerCollection
    {
        $customerList = new CustomerCollection;

        if (! empty($customers)) {
            foreach (($customers['Customers'] ?? []) as $customer) {
                $customerList->push($this->renderSingleCustomer($customer));
            }
        }

        return $customerList;
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     */
    public function getCustomerDetail(array $customer = []): Customer
    {
        if (! isset($customer['Customers']) || empty($customer['Customers'])) {
            $customer = [];
        } else {
            $customer = array_shift($customer['Customers']);
        }

        return $this->renderSingleCustomer($customer);
    }

    /**
     * This API is to get customer ship to locations entity information from the FACTS ERP
     */
    public function validateCustomerShippingLocation(array $location = []): ShippingLocationValidation
    {
        $locationInfo = isset($location['AddressVerification'])
            ? array_shift($location['AddressVerification'])
            : [];

        return $this->renderValidatedSingleCustomerShippingLocation($locationInfo);
    }

    /**
     * This API is to get customer ship to locations entity information from the FACTS ERP
     */
    public function createCustomerShippingLocation(array $location = []): ShippingLocation
    {
        $locationInfo = isset($location['ShipTo'])
            ? array_shift($location['ShipTo'])
            : [];

        return $this->renderSingleCustomerShippingLocation($locationInfo);
    }

    /**
     * This API is to get customer ship to locations entity information from the FACTS ERP
     */
    public function getCustomerShippingLocationList(array $locations = []): ShippingLocationCollection
    {
        $customerShippingLocations = new ShippingLocationCollection;

        if (! empty($locations)) {
            foreach ($locations['ShipTo'] as $location) {
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
        $model = new ProductPriceAvailabilityCollection;

        if (! empty($filters)) {
            foreach (($filters['Items'] ?? []) as $item) {
                $model->push($this->renderSingleProductPriceAvailability($item));
            }
        }

        return $model;
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getProductSync(array $filters = []): ProductSyncCollection
    {
        $model = new ProductSyncCollection;

        // Mapping
        if (! empty($filters)) {
            $model->RestartPoint = $filters['RestartPoint'] ?? null;
            foreach (($filters['ItemMaster'] ?? []) as $item) {
                if ($item['Item'][0]) {
                    $model->push($this->renderProductSync($item['Item'][0]));
                } else {
                    $model->push($this->renderProductSync($item));
                }
            }
        }

        return $model;
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
        return $this->getOrderDetail($orderInfo);
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderList(array $customerOrders = []): OrderCollection
    {
        $orders = new OrderCollection;
        if (! empty($customerOrders)) {
            foreach (($customerOrders['Orders'] ?? []) as $order) {
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
        $orderInfo = isset($orderInfo['Orders'])
            ? array_shift($orderInfo['Orders'])
            : [];

        return $this->renderSingleOrder($orderInfo);
    }

    /**
     * This API is to create a quotation in the FACTS ERP
     */
    public function createQuotation(array $orderInfo = []): CreateQuotationCollection
    {
        $quoteCollection = new CreateQuotationCollection;

        if (! empty($orderInfo)) {
            foreach (($orderInfo['Order'] ?? []) as $quote) {
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

        if (! empty($customerOrders)) {
            foreach (($customerOrders['Quotes'] ?? []) as $quote) {
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
        $orderInfo = isset($orderInfo['Quotes'])
            ? array_shift($orderInfo['Quotes'])
            : [];

        return $this->renderSingleQuotation($orderInfo);
    }

    /**
     * This API is to get customer Accounts Receivables information from the FACTS ERP
     */
    public function getCustomerARSummary(array $attributes = []): CustomerAR
    {
        $model = new CustomerAR($attributes);

        if (! empty($attributes)) {
            $attributes = $attributes['ARSummary'] ?? [];

            $model->CustomerNum = $attributes['CustomerNum'] ?? null;
            $model->CustomerName = $attributes['CustomerName'] ?? null;
            $model->Address1 = $attributes['Address1'] ?? null;
            $model->Address2 = $attributes['Address2'] ?? null;
            $model->City = $attributes['City'] ?? null;
            $model->ZipCode = $attributes['ZipCode'] ?? null;
            $model->State = $attributes['State'] ?? null;
            $model->AgeDaysPeriod1 = $attributes['AgeDaysPeriod1'] ?? null;
            $model->AgeDaysPeriod2 = $attributes['AgeDaysPeriod2'] ?? null;
            $model->AgeDaysPeriod3 = $attributes['AgeDaysPeriod3'] ?? null;
            $model->AgeDaysPeriod4 = $attributes['AgeDaysPeriod4'] ?? null;
            $model->AgeDaysPeriod5 = $attributes['AgeDaysPeriod5'] ?? null;
            $model->AmountDue = $attributes['AmountDue'] ?? null;
            $model->BillingPeriodAmount = $attributes['BillingPeriodAmount'] ?? null;
            $model->DateOfFirstSale = $attributes['DateOfFirstSale'] ?? null;
            $model->DateOfLastPayment = $attributes['DateOfLastPayment'] ?? null;
            $model->DateOfLastSale = $attributes['DateOfLastSale'] ?? null;
            $model->FutureAmount = $attributes['FutureAmount'] ?? null;
            $model->OpenOrderAmount = $attributes['OpenOrderAmount'] ?? null;
            $model->SalesLastYearToDate = $attributes['SalesLastYearToDate'] ?? null;
            $model->SalesMonthToDate = $attributes['SalesMonthToDate'] ?? null;
            $model->SalesYearToDate = $attributes['SalesYearToDate'] ?? null;
            $model->TermsCode = $attributes['TermsCode'] ?? null;
            $model->TermsDescription = $attributes['TermsDescription'] ?? null;
            $model->TradeAgePeriod1Amount = $attributes['TradeAgePeriod1Amount'] ?? null;
            $model->TradeAgePeriod2Amount = $attributes['TradeAgePeriod2Amount'] ?? null;
            $model->TradeAgePeriod3Amount = $attributes['TradeAgePeriod3Amount'] ?? null;
            $model->TradeAgePeriod4Amount = $attributes['TradeAgePeriod4Amount'] ?? null;
            $model->TradeAgePeriod5Amount = $attributes['TradeAgePeriod5Amount'] ?? null;
            $model->TradeAmountDue = $attributes['TradeAmountDue'] ?? null;
            $model->TradeBillingPeriodAmount = $attributes['TradeBillingPeriodAmount'] ?? null;
            $model->AvgDaysToPay1 = $attributes['AvgDaysToPay1'] ?? null;
            $model->AvgDaysToPay1Wgt = $attributes['AvgDaysToPay1Wgt'] ?? null;
            $model->AvgDaysToPay2 = $attributes['AvgDaysToPay2'] ?? null;
            $model->AvgDaysToPay2Wgt = $attributes['AvgDaysToPay2Wgt'] ?? null;
            $model->AvgDaysToPay3 = $attributes['AvgDaysToPay3'] ?? null;
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
            $model->TradeAgePeriod1Text = $attributes['TradeAgePeriod1Text'] ?? null;
            $model->TradeAgePeriod2Text = $attributes['TradeAgePeriod2Text'] ?? null;
            $model->TradeAgePeriod3Text = $attributes['TradeAgePeriod3Text'] ?? null;
            $model->TradeAgePeriod4Text = $attributes['TradeAgePeriod4Text'] ?? null;
            $model->TradeAgePeriod5Text = $attributes['TradeAgePeriod5Text'] ?? null;
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

        if (! empty($attributes)) {
            foreach (($attributes['Invoices'] ?? []) as $invoice) {
                $invoiceList->push($this->renderSingleInvoice($invoice));
            }
        }

        return $invoiceList;
    }

    /**
     * This API is to get customer AR Open Invoice data from the FACTS ERP.
     */
    public function getInvoiceDetail(array $data = []): Invoice
    {
        $data = isset($data['Invoices'])
            ? array_shift($data['Invoices'])
            : [];

        return $this->renderSingleInvoice($data);
    }

    /**
     * This API is to create a AR payment on the customers account.
     */
    public function createPayment(array $paymentInfo = []): CreatePayment
    {
        $model = new CreatePayment($paymentInfo);

        if (! empty($paymentInfo['ArPayment'])) {
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

        if (! empty($noteInfo['UpdateNotes'])) {
            $attributes = $noteInfo['UpdateNotes'] ?? [];

            $model->Status = $attributes['NoteNum'] ?? null;
            $model->NoteNum = $attributes['NoteNum'] ?? null;
        }

        return $model;
    }

    private function renderSingleCustomer(array $attributes): Customer
    {
        $model = new Customer($attributes);

        if (! empty($attributes)) {
            $model->CustomerNumber = $attributes['CustomerNumber'] ?? null;
            $model->ArCustomerNumber = $attributes['ArCustomerNumber'] ?? null;
            $model->CustomerName = $attributes['CustomerName'] ?? null;
            $model->CustomerCountry = $attributes['CustomerCountry'] ?? null;
            $model->CustomerAddress1 = $attributes['CustomerAddress1'] ?? null;
            $model->CustomerAddress2 = $attributes['CustomerAddress2'] ?? null;
            $model->CustomerAddress3 = $attributes['CustomerAddress3'] ?? null;
            $model->CustomerCity = $attributes['CustomerCity'] ?? null;
            $model->CustomerState = $attributes['CustomerState'] ?? null;
            $model->CustomerZipCode = $attributes['CustomerZipCode'] ?? null;
            $model->CustomerEmail = $attributes['CustomerEmail'] ?? null;
            $model->CustomerPhone = $attributes['CustomerPhone'] ?? null;
            $model->CustomerContact = $attributes['CustomerContact'] ?? null;
            $model->DefaultShipTo = $attributes['DefaultShipTo'] ?? null;
            $model->DefaultWarehouse = $attributes['DefaultWarehouse'] ?? null;
            $model->CarrierCode = $attributes['CarrierCode'] ?? null;
            $model->PriceList = $attributes['PriceList'] ?? null;
            $model->BackorderCode = $attributes['BackorderCode'] ?? null;
            $model->CustomerClass = $attributes['CustomerClass'] ?? null;
            $model->SuspendCode = $attributes['SuspendCode'] ?? null;
            $model->AllowArPayments = $attributes['AllowArPayments'] ?? null;
            $model->CreditCardOnly = $attributes['CreditCardOnly'] ?? null;
            $model->FreightOptionAmount = ! empty($attributes['FreightOptionAmount']) ? floatval($attributes['FreightOptionAmount']) : null;
            $model->PoRequired = $attributes['PoRequired'] ?? null;
            $model->SalesPersonCode = $attributes['SalesPersonCode'] ?? null;
            $model->SalesPersonName = $attributes['SalesPersonName'] ?? null;
            $model->SalesPersonEmail = $attributes['SalesPersonEmail'] ?? null;
            $model->OTShipPrice = $attributes['OTShipPrice'] ?? null;
        }

        return $model;
    }

    public function renderValidatedSingleCustomerShippingLocation($attributes): ShippingLocationValidation
    {
        $model = new ShippingLocationValidation($attributes);

        if (! empty($attributes)) {
            $model->Name = $attributes['Name'] ?? null;
            $model->Address1 = $attributes['Address1'] ?? null;
            $model->Address2 = $attributes['Address2'] ?? null;
            $model->Address3 = $attributes['Address3'] ?? null;
            $model->City = $attributes['City'] ?? null;
            $model->State = $attributes['State'] ?? null;
            $model->ZipCode = $attributes['ZipCode'] ?? null;
            $model->Status = $attributes['Status'] ?? null;
            $model->Response = $attributes['Response'] ?? null;
            $model->Message = $attributes['Message'] ?? null;
            $model->Details = $attributes['Details'] ?? null;
            $model->Reference = $attributes['Reference'] ?? null;
        }

        return $model;
    }

    public function renderSingleCustomerShippingLocation($attributes): ShippingLocation
    {
        $model = new ShippingLocation($attributes);

        if (! empty($attributes)) {
            $model->ShipToNumber = $attributes['ShipToNumber'] ?? null;
            $model->ShipToName = $attributes['ShipToName'] ?? null;
            $model->ShipToCountryCode = $attributes['ShipToCountryCode'] ?? null;
            $model->ShipToAddress1 = $attributes['ShipToAddress1'] ?? null;
            $model->ShipToAddress2 = $attributes['ShipToAddress2'] ?? null;
            $model->ShipToAddress3 = $attributes['ShipToAddress3'] ?? null;
            $model->ShipToCity = $attributes['ShipToCity'] ?? null;
            $model->ShipToState = $attributes['ShipToState'] ?? null;
            $model->ShipToZipCode = $attributes['ShipToZipCode'] ?? null;
            $model->ShipToPhoneNumber = $attributes['ShipToPhoneNumber'] ?? null;
            $model->ShipToContact = $attributes['ShipToContact'] ?? null;
            $model->ShipToWarehouse = $attributes['ShipToWarehouse'] ?? null;
            $model->BackorderCode = $attributes['BackorderCode'] ?? null;
            $model->CarrierCode = $attributes['CarrierCode'] ?? null;
            $model->PoRequired = $attributes['PoRequired'] ?? null;
        }

        return $model;
    }

    private function renderSingleProductPriceAvailability($attributes): ProductPriceAvailability
    {
        $model = new ProductPriceAvailability($attributes);

        if (! empty($attributes)) {
            $attributes = (! empty($attributes['Item']))
                ? array_shift($attributes['Item'])
                : [];

            $model->ItemNumber = $attributes['ItemNumber'] ?? null;
            $model->WarehouseID = $attributes['WarehouseID'] ?? null;
            $model->Price = ($attributes['Price']) ? (float) str_replace(',', '', $attributes['Price']) : 0;
            $model->ListPrice = ($attributes['ListPrice']) ? (float) str_replace(',', '', $attributes['ListPrice']) : 0;
            $model->StandardPrice = ($attributes['StandardPrice']) ? (float) str_replace(',', '', $attributes['StandardPrice']) : 0;
            $model->QtyPrice_1 = $attributes['QtyPrice_1'] ?? null;
            $model->QtyBreak_1 = $attributes['QtyBreak_1'] ?? null;
            $model->QtyPrice_2 = $attributes['QtyPrice_2'] ?? null;
            $model->QtyBreak_2 = $attributes['QtyBreak_2'] ?? null;
            $model->QtyPrice_3 = $attributes['QtyPrice_3'] ?? null;
            $model->QtyBreak_3 = $attributes['QtyBreak_3'] ?? null;
            $model->QtyPrice_4 = $attributes['QtyPrice_4'] ?? null;
            $model->QtyBreak_4 = $attributes['QtyBreak_4'] ?? null;
            $model->QtyPrice_5 = $attributes['QtyPrice_5'] ?? null;
            $model->QtyBreak_5 = $attributes['QtyBreak_5'] ?? null;
            $model->QtyPrice_6 = $attributes['QtyPrice_6'] ?? null;
            $model->QtyBreak_6 = $attributes['QtyBreak_6'] ?? null;
            $model->ExtendedPrice = $attributes['ExtendedPrice'] ?? null;
            $model->OrderPrice = $attributes['OrderPrice'] ?? null;
            $model->UnitOfMeasure = $attributes['UnitOfMeasure'] ?? null;
            $model->PricingUnitOfMeasure = ucwords(strtolower($attributes['UnitOfMeasureDescription'] ?? null));
            $model->DefaultSellingUnitOfMeasure = $attributes['DefaultSellingUnitOfMeasure'] ?? null;
            $model->AverageLeadTime = $attributes['AverageLeadTime'] ?? null;
            $model->QuantityAvailable = $attributes['QuantityAvailable'] ?? null;
            $model->QuantityOnOrder = $attributes['QuantityOnOrder'] ?? null;
            $model->Warehouses = $attributes['Warehouses'] ?? [];
        }

        return $model;
    }

    private function renderProductSync($attributes): ProductSync
    {
        $model = new ProductSync($attributes);

        $model->ItemNumber = $attributes['ItemNumber'] ?? null;
        $model->SubAction = null;
        $model->UpdateAction = $attributes['UpdateAction'] ?? null;
        $model->Description1 = $attributes['ItemCatalogDescription'] ?? null;
        $model->Description2 = $attributes['GroupDescription'] ?? null;
        $model->ItemClass = $attributes['ItemClass'] ?? null;
        $model->PriceClass = $attributes['PriceClass'] ?? null;
        $model->ListPrice = isset($attributes['DefaultPrice']) ? (float) str_replace(',', '', $attributes['DefaultPrice']) : 0;
        $model->UnitOfMeasure = $attributes['UnitOfMeasure'] ?? null;
        $model->PricingUnitOfMeasure = $attributes['PricingUnitOfMeasure'] ?? null;
        $model->Manufacturer = $attributes['VendorName'] ?? null;
        $model->PrimaryVendor = $attributes['PrimaryVendor'] ?? null;
        $model->BrandName = $attributes['BrandName'] ?? null;
        $model->ImagePath = $attributes['ImagePath'] ?? null;
        $model->ItemID = $attributes['ItemID'] ?? null;
        $model->ItemGroup = $attributes['ItemGroup'] ?? null;

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
        $model->WarehouseEmail = $warehouse['email'] ?? null;

        $model->WhsSeqCode = $warehouse['WhsSeqCode'] ?? null;
        $model->CompanyNumber = $warehouse['CompanyNumber'] ?? null;
        $model->WhPricingLevel = $warehouse['WhPricingLevel'] ?? null;

        return $model;
    }

    private function renderSingleCreateOrder($attributes): Order
    {
        $model = new Order($attributes);

        if (! empty($attributes)) {
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
            $model->OrderNotes = new OrderNoteCollection;

            if (! empty($attributes['OrderDetail'])) {
                foreach (($attributes['OrderDetail'] ?? []) as $orderDetail) {
                    $model->OrderDetail->push($this->renderSingleOrderDetail($orderDetail));
                }
            }

            if (! empty($attributes['OrderNotes'])) {
                foreach (($attributes['OrderNotes'] ?? []) as $orderNote) {
                    $model->OrderNotes->push($this->renderSingleOrderNote($orderNote));
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

    private function renderSingleOrder($attributes): Order
    {
        $model = new Order($attributes);

        if (! empty($attributes)) {
            $model->CustomerNumber = $attributes['CustomerNumber'] ?? null;
            $model->ContactId = $attributes['ContactId'] ?? null;
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
            $model->FreightAmount = floatval(str_replace(['$', ','], '', ($attributes['FreightAmount'] ?? '')));
            $model->TotalOrderValue = $attributes['TotalOrderValue'] ?? null;
            $model->TotalSpecialCharges = $attributes['TotalSpecialCharges'] ?? null;
            $model->CarrierCode = $attributes['CarrierCode'] ?? null;
            $model->WarehouseID = $attributes['WarehouseID'] ?? null;
            $model->InvoiceNumber = $attributes['InvoiceNumber'] ?? null;
            $model->EmailAddress = $attributes['EmailAddress'] ?? null;
            $model->PdfAvailable = $attributes['PdfAvailable'] ?? null;
            $model->OrderDetail = new OrderDetailCollection;
            $model->OrderNotes = new OrderNoteCollection;

            if (! empty($attributes['OrderDetail'])) {
                foreach (($attributes['OrderDetail'] ?? []) as $orderDetail) {
                    $model->OrderDetail->push($this->renderSingleOrderDetail($orderDetail));
                }
            }

            if (! empty($attributes['OrderNotes'])) {
                foreach (($attributes['OrderNotes'] ?? []) as $orderNote) {
                    $model->OrderNotes->push($this->renderSingleOrderNote($orderNote));
                }
            }
        }

        return $model;
    }

    private function renderSingleOrderNote($attributes): OrderNote
    {
        $model = new OrderNote($attributes);

        if (! empty($attributes)) {
            $model->Subject = $attributes['Subject'] ?? null;
            $model->Category = $attributes['Category'] ?? null;
            $model->Date = isset($attributes['Date']) ? CarbonImmutable::parse($attributes['Date']) : null;
            $model->NoteNum = $attributes['NoteNum'] ?? null;
            $model->Type = $attributes['Type'] ?? null;
            $model->Editable = $attributes['Editable'] ?? null;
            $model->Note = $attributes['Note'] ?? null;
        }

        return $model;

    }

    private function renderSingleOrderDetail($attributes): OrderDetail
    {
        $model = new OrderDetail($attributes);

        if (! empty($attributes)) {
            $model->LineNumber = $attributes['LineNumber'] ?? null;
            $model->ItemNumber = $attributes['ItemNumber'] ?? null;
            $model->ItemId = $attributes['ItemId'] ?? null;
            $model->ItemType = $attributes['ItemType'] ?? null;
            $model->ItemDescription1 = $attributes['ItemDescription1'] ?? null;
            $model->ItemDescription2 = $attributes['ItemDescription2'] ?? null;
            $model->QuantityOrdered = (isset($attributes['QuantityOrdered'])) ? $attributes['QuantityOrdered'] : ((isset($attributes['Quantity'])) ? $attributes['Quantity'] : null);
            $model->QuantityShipped = $attributes['QuantityShipped'] ?? null;
            $model->QuantityBackordered = $attributes['QuantityBackordered'] ?? null;
            $model->UnitOfMeasure = $attributes['UnitOfMeasure'] ?? null;
            $model->PricingUM = $attributes['PricingUM'] ?? null;
            $model->ActualSellPrice = ($attributes['ActualSellPrice']) ? (float) str_replace(',', '', $attributes['ActualSellPrice']) : 0;
            $model->ShipWhse = $attributes['ShipWhse'] ?? null;
            $model->TotalLineAmount = ($attributes['TotalLineAmount']) ? (float) str_replace(',', '', $attributes['TotalLineAmount']) : 0;
            $model->LineGroupId = $attributes['LineGroupId'] ?? null;
            $model->LineGroupType = $attributes['LineGroupType'] ?? null;
            $model->LogoPosition = $attributes['LogoPosition'] ?? null;
            $model->LogoId = $attributes['LogoId'] ?? null;
        }

        return $model;
    }

    private function renderSingleCreateQuotation($attributes): CreateQuotation
    {
        $model = new CreateQuotation($attributes);

        if (! empty($attributes)) {
            $model->OrderNumber = floatval(str_replace(['$', ','], '', ($attributes['OrderNumber'] ?? '')));
            $model->SalesTaxAmount = floatval(str_replace(['$', ','], '', ($attributes['SalesTaxAmount'] ?? '')));
            $model->FreightAmount = floatval(str_replace(['$', ','], '', ($attributes['FreightAmount'] ?? '')));
            $model->TotalOrderValue = floatval(str_replace(['$', ','], '', ($attributes['TotalOrderValue'] ?? '')));
        }

        return $model;
    }

    private function renderSingleQuotation($attributes): Quotation
    {
        $model = new Quotation($attributes);

        if (! empty($attributes)) {
            $model->CustomerNumber = $attributes['CustomerNumber'] ?? null;
            $model->ContactId = $attributes['ContactId'] ?? null;
            $model->QuoteNumber = $attributes['QuoteNumber'] ?? null;
            $model->QuoteType = $attributes['QuoteType'] ?? null;
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
            $model->EffectiveDate = $attributes['EffectiveDate'] ?? null;
            $model->ExpirationDate = $attributes['ExpirationDate'] ?? null;
            $model->RequestedShipDate = $attributes['RequestedShipDate'] ?? null;
            $model->CustomerPurchaseOrdernumber = $attributes['CustomerPurchaseOrdernumber'] ?? null;
            $model->ItemSalesAmount = $attributes['ItemSalesAmount'] ?? null;
            $model->DiscountAmountTrading = $attributes['DiscountAmountTrading'] ?? null;
            $model->SalesTaxAmount = $attributes['SalesTaxAmount'] ?? null;
            $model->TotalOrderValue = $attributes['TotalOrderValue'] ?? null;
            $model->TotalSpecialCharges = $attributes['TotalSpecialCharges'] ?? null;
            $model->CarrierCode = $attributes['CarrierCode'] ?? null;
            $model->WarehouseID = $attributes['WarehouseID'] ?? null;
            $model->QuoteAmount = $attributes['QuoteAmount'] ?? null;
            $model->EmailAddress = $attributes['EmailAddress'] ?? null;
            $model->BillToCountryName = $attributes['BillToCountryName'] ?? null;
            $model->ShipToCountryName = $attributes['ShipToCountryName'] ?? null;
            $model->PdfAvailable = $attributes['PdfAvailable'] ?? null;
            $model->QuoteDetail = new OrderDetailCollection;
            $model->QuotedTo = $attributes['QuotedTo'] ?? null;

            if (! empty($attributes['QuoteDetail'])) {
                foreach (($attributes['QuoteDetail'] ?? []) as $orderDetail) {
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

    private function renderSingleInvoice($attributes): Invoice
    {
        $model = new Invoice($attributes);

        if (! empty($attributes)) {
            $model->InvoiceNumber = $attributes['InvoiceNumber'] ?? null;
            $model->InvoiceType = $attributes['InvoiceType'] ?? null;
            $model->InvoiceDisputeCode = $attributes['InvoiceDisputeCode'] ?? null;
            $model->FinanceChargeFlag = $attributes['FinanceChargeFlag'] ?? null;
            $model->InvoiceDate = $attributes['InvoiceDate'] ?? null;
            $model->AgeDate = $attributes['AgeDate'] ?? null;
            $model->EntryDate = $attributes['EntryDate'] ?? null;
            $model->InvoiceAmount = $attributes['InvoiceAmount'] ?? null;
            $model->InvoiceBalance = $attributes['InvoiceBalance'] ?? null;
            $model->PendingPayment = $attributes['PendingPayment'] ?? null;
            $model->DiscountAmount = $attributes['DiscountAmount'] ?? null;
            $model->DiscountDueDate = $attributes['DiscountDueDate'] ?? null;
            $model->LastTransactionDate = $attributes['LastTransactionDate'] ?? null;
            $model->PayDays = $attributes['PayDays'] ?? null;
            $model->AllowArPayments = $attributes['AllowArPayments'] ?? null;
            $model->CustomerPONumber = $attributes['CustomerPONumber'] ?? null;
            $model->HasInvoiceDetail = $attributes['InvoiceDetail'] ?? 'No';
            $model->InvoiceDetail = new OrderCollection;

            if (isset($attributes['InvoiceDetail']) && is_array($attributes['InvoiceDetail'])) {
                $model->InvoiceDetail = $this->getOrderList($attributes['InvoiceDetail']);
            }
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

        if (! empty($attributes)) {
            foreach (($attributes['ItemPromoHeader'] ?? []) as $campaign) {
                $campaignList->push($this->renderSingleCampaign($campaign));
            }
        }

        return $campaignList;
    }

    public function getCampaignDetail(array $data = []): Campaign
    {
        $data = isset($data['ItemPromoHeader'])
            ? array_shift($data['ItemPromoHeader'])
            : [];

        return $this->renderSingleCampaign($data);
    }

    private function renderSingleCampaign($attributes): Campaign
    {
        $model = new Campaign($attributes);

        if (! empty($attributes)) {
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

            if (! empty($attributes['ItemPromoDetails'])) {
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

        if (! empty($attributes)) {
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

        $attributes = [];

        if (! empty($inputs['ContactValidation'])) {
            $attributes = array_shift($inputs['ContactValidation']);
        }

        $model->ValidCombination = $attributes['ValidCombination'] ?? 'N';
        $model->CustomerNumber = $attributes['CustomerNumber'] ?? 'N';
        $model->ContactNumber = $attributes['ContactNumber'] ?? 'N';
        $model->EmailAddress = $attributes['EmailAddress'] ?? 'N';
        $model->DefaultWarehouse = $attributes['DefaultWarehouse'] ?? 'N';
        $model->DefaultShipTo = $attributes['DefaultShipTo'] ?? 'N';

        return $model;
    }

    public function getDocument(array $inputs = []): Document
    {
        return new Document($inputs);
    }

    public function getPastItemList(array $attributes = []): PastItemCollection
    {
        return new PastItemCollection;
    }

    /**
     * This API is to get details of an order/invoice,
     * or list of orders from a date range
     */
    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderTotal(array $orderInfo = []): OrderTotal
    {

        $attributes = isset($orderInfo['Order'])
            ? array_shift($orderInfo['Order'])
            : [];

        $model = new OrderTotal($attributes);

        $model->OrderNumber = $attributes['OrderNumber'] ?? null;
        $model->TotalOrderValue = $attributes['TotalOrderValue'] ?? null;
        $model->SalesTaxAmount = $attributes['SalesTaxAmount'] ?? null;
        $model->FreightAmount = $attributes['FreightAmount'] ?? null;
        $model->FreightRate = $attributes['FreightRate'] ?? null;

        return $model;
    }

    public function getTrackShipment(array $inputs = []): TrackShipmentCollection
    {
        $model = new TrackShipmentCollection;

        foreach (($attributes ?? []) as $trackingInfo) {
            $model->push($this->renderSingleTrackShipment($trackingInfo));
        }

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

    public function renderSingleTrackShipment($attributes = []): TrackShipment
    {
        $model = new TrackShipment($attributes);

        $model->OrderNumber = $attributes['orderno'] ?? null;
        $model->TrackerNo = $attributes['trackerno'] ?? null;
        $model->ShipViaType = $attributes['shipviaty'] ?? null;

        return $model;
    }
}
